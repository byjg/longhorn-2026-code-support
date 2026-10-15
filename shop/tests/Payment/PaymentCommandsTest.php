<?php

namespace Tests\Payment;

use ByJG\Config\Config;
use ByJG\WebRequest\MockClient;
use ByJG\WebRequest\Psr7\MemoryStream;
use ByJG\WebRequest\Psr7\Response;
use Shop\Command\PaymentCommands;
use Shop\Gateway\GatewayClient;
use Shop\Model\Payment;
use Shop\Payment\PaymentState;
use Shop\Payment\PaymentService;
use Shop\Repository\PaymentRepository;
use Tests\Controller\BaseApiTestCase;

/**
 * The ./gluo payment:* doors. The gateway is a MockClient, so these run without the
 * gateway container; everything else is the real workflow on the test database.
 */
class PaymentCommandsTest extends BaseApiTestCase
{
    private PaymentRepository $payments;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->payments = Config::get(PaymentRepository::class);
    }

    public function testCreateRegistersThePaymentAtTheGatewayAndSubmitsIt(): void
    {
        $http = new MockClient(Response::getInstance(200)->withBody(new MemoryStream('{"id": 77}')));

        $this->expectOutputRegex('/#(\d+) create: CREATED → PENDING/');
        $this->commands($http)->create(25.5);

        $sent = json_decode((string)$http->getRequestedObject()->getBody(), true);
        $this->assertSame('http://gateway/transactions', (string)$http->getRequestedObject()->getUri());
        $this->assertSame(25.5, $sent['amount']);
        $this->assertSame('http://shop/webhook', $sent['webhook_url']);

        $payment = $this->payments->get($sent['payment_id']);
        $this->assertSame('PENDING', $payment->status);
        $this->assertSame('77', $payment->gatewayRef);
    }

    public function testAPaymentTheGatewayRefusedStaysCreated(): void
    {
        $http = new MockClient(Response::getInstance(500)->withBody(new MemoryStream('{"error": "down"}')));

        $this->expectOutputRegex('/stays CREATED — the gateway did not take it/');
        $this->commands($http)->create(10.0);

        $sent = json_decode((string)$http->getRequestedObject()->getBody(), true);
        $this->assertSame('CREATED', $this->payments->get($sent['payment_id'])->status);
    }

    public function testTheRetryJobCannotChargeTwice(): void
    {
        $payment = new Payment();
        $payment->amount = 10.0;
        $payment->status = PaymentState::Paid;
        $this->payments->save($payment);
        $id = (int)$payment->id;

        $this->expectOutputString("#{$id} retry: PAID -/-> PAID refused: no arrow PAID → PAID" . PHP_EOL);
        $this->commands(new MockClient())->retry($id);
    }

    private function commands(MockClient $http): PaymentCommands
    {
        return new PaymentCommands(
            Config::get(PaymentService::class),
            new GatewayClient($http, 'http://gateway', 'http://shop/webhook')
        );
    }
}
