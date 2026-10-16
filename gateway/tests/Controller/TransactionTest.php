<?php

namespace Tests\Controller;

use ByJG\Gluo\Util\FakeApiRequester;
use ByJG\RestServer\Exception\Error404Exception;

/**
 * The gateway's HTTP side, run in-process against openapi.json.
 */
class TransactionTest extends BaseApiTestCase
{
    public function testTheShopRegistersAPayment(): void
    {
        $request = (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath('/transactions')
            ->withRequestBody(json_encode(['payment_id' => 42, 'amount' => 99.9, 'webhook_url' => 'http://shop/webhook']))
            ->expectStatus(200);

        $body = json_decode($this->sendRequest($request)->getBody()->getContents(), true);

        $this->assertIsInt($body['id']);
        $this->assertStringContainsString('Payment #42', $this->page());
    }

    public function testThePageListsNothingYetWithAHint(): void
    {
        $this->assertStringContainsString('<title>Gateway</title>', $this->page());
    }

    public function testAButtonOnAnUnknownTransactionIs404(): void
    {
        $this->expectException(Error404Exception::class);

        $request = (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('POST')
            ->withPath('/transactions/999999/approve')
            ->expectStatus(404);
        $this->sendRequest($request);
    }

    /**
     * GET /operator, validated against openapi.json like any other route (a text/html response).
     */
    private function page(): string
    {
        $request = (new FakeApiRequester())
            ->withPsr7Request($this->getPsr7Request())
            ->withMethod('GET')
            ->withPath('/operator')
            ->expectStatus(200);

        return $this->sendRequest($request)->getBody()->getContents();
    }
}
