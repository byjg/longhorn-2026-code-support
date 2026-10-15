<?php

namespace Tests\Gateway;

use ByJG\Config\Config;
use ByJG\WebRequest\MockClient;
use ByJG\WebRequest\Psr7\MemoryStream;
use ByJG\WebRequest\Psr7\Response;
use Gateway\Gateway\GatewayService;
use Gateway\Repository\TransactionRepository;
use Gateway\Repository\WebhookCallRepository;
use InvalidArgumentException;
use Tests\Controller\BaseApiTestCase;

/**
 * The shop is a MockClient here: these check what the gateway sends and that it keeps the
 * answer, on the test database.
 */
class GatewayServiceTest extends BaseApiTestCase
{
    public function testAButtonReportsTheEventAndKeepsTheShopsAnswer(): void
    {
        $http = new MockClient(Response::getInstance(200)->withBody(new MemoryStream('{"payment_id":7,"from":"PENDING","to":"ANALYSIS"}')));
        $gateway = $this->gateway($http);
        $transaction = $gateway->register(7, 100.0, 'http://shop/webhook');

        $call = $gateway->act((int)$transaction->getId(), 'review');

        $this->assertSame('http://shop/webhook', (string)$http->getRequestedObject()->getUri());
        $this->assertSame(['payment_id' => 7, 'gateway' => 'requires_review'], json_decode((string)$http->getRequestedObject()->getBody(), true));
        $this->assertSame(200, $call->getResponseStatus());
        $this->assertSame('{"payment_id":7,"from":"PENDING","to":"ANALYSIS"}', $call->getResponseBody());
        $this->assertSame('requires_review', Config::get(TransactionRepository::class)->get($transaction->getId())->getStatus());
    }

    public function testResendRepeatsTheLastEvent(): void
    {
        $http = new MockClient(Response::getInstance(200)->withBody(new MemoryStream('{}')));
        $gateway = $this->gateway($http);
        $id = (int)$gateway->register(8, 10.0, 'http://shop/webhook')->getId();

        $gateway->act($id, 'approve');
        $call = $gateway->act($id, 'resend');

        $this->assertSame('succeeded', $call->getEvent());
        $this->assertCount(2, Config::get(WebhookCallRepository::class)->forTransaction($id));
    }

    public function testThereIsNothingToResendBeforeTheFirstEvent(): void
    {
        $gateway = $this->gateway(new MockClient());
        $id = (int)$gateway->register(9, 10.0, 'http://shop/webhook')->getId();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has no event to resend');
        $gateway->act($id, 'resend');
    }

    public function testAnUnknownActionIsRejected(): void
    {
        $gateway = $this->gateway(new MockClient());
        $id = (int)$gateway->register(10, 10.0, 'http://shop/webhook')->getId();

        $this->expectException(InvalidArgumentException::class);
        $gateway->act($id, 'refund');
    }

    private function gateway(MockClient $http): GatewayService
    {
        return new GatewayService(Config::get(TransactionRepository::class), Config::get(WebhookCallRepository::class), $http);
    }
}
