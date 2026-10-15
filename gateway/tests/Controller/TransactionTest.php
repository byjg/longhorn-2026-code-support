<?php

namespace Tests\Controller;

use ByJG\Config\Config;
use ByJG\Gluo\Util\FakeApiRequester;
use ByJG\RestServer\Exception\Error404Exception;
use Gateway\Gateway\GatewayPage;

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

    // GET / is rendered directly: swagger-test cannot validate a text/html response (it
    // detects the content type but does not pass it to the body matcher). The live route
    // is checked over HTTP.
    private function page(): string
    {
        return Config::get(GatewayPage::class)->render();
    }
}
