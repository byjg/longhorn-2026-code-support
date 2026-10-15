<?php

namespace Tests\Controller;

use ByJG\ApiTools\Exception\NotMatchedException;
use ByJG\Config\Config;
use ByJG\Gluo\Util\FakeApiRequester;
use ByJG\RestServer\Exception\Error404Exception;
use Shop\Model\Payment;
use Shop\Payment\PaymentState;
use Shop\Repository\PaymentRepository;

/**
 * POST /webhook, run in-process: the request is validated against openapi.json, the
 * workflow decides, and the answer tells the gateway where the payment went.
 */
class WebhookTest extends BaseApiTestCase
{
    public function testTheWebhookMovesThePayment(): void
    {
        $id = $this->paymentIn(PaymentState::Pending);

        $body = $this->post(['payment_id' => $id, 'gateway' => 'requires_review'], 200);

        $this->assertSame(['payment_id' => $id, 'from' => 'PENDING', 'to' => 'ANALYSIS'], $body);
        $this->assertSame('ANALYSIS', Config::get(PaymentRepository::class)->get($id)->status);
    }

    public function testARefusedMoveIsStillA200WithTheReason(): void
    {
        $id = $this->paymentIn(PaymentState::Analysis);

        $body = $this->post(['payment_id' => $id, 'gateway' => 'expired'], 200);

        $this->assertSame('refused', $body['result']);
        $this->assertSame('ANALYSIS→PAID (RiskApproved: no), ANALYSIS→FAILED (RiskRejected: no)', $body['reason']);
        $this->assertSame('ANALYSIS', Config::get(PaymentRepository::class)->get($id)->status);
    }

    public function testAnUnknownPaymentIs404(): void
    {
        $this->expectException(Error404Exception::class);
        $this->post(['payment_id' => 999999, 'gateway' => 'succeeded'], 404);
    }

    // The contract only knows four events. The test harness checks the request against
    // openapi.json before sending it, so the mismatch is reported here, client side.
    public function testAnEventTheContractDoesNotKnowIsRejected(): void
    {
        $this->expectException(NotMatchedException::class);
        $this->expectExceptionMessage("Value 'teleported' in 'gateway' not matched in ENUM");
        $this->post(['payment_id' => 1, 'gateway' => 'teleported'], 422);
    }

    private function post(array $payload, int $status): array
    {
        $request = (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath('/webhook')
            ->withRequestBody(json_encode($payload))
            ->expectStatus($status);

        return json_decode($this->sendRequest($request)->getBody()->getContents(), true);
    }

    private function paymentIn(PaymentState $state): int
    {
        $payment = new Payment();
        $payment->amount = 10.0;
        $payment->status = $state;
        Config::get(PaymentRepository::class)->save($payment);

        return (int)$payment->id;
    }
}
