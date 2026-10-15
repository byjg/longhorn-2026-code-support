<?php

namespace Gateway\Controller;

use ByJG\Gluo\Attribute\ValidateRequest;
use ByJG\RestServer\Exception\Error404Exception;
use ByJG\RestServer\HttpRequest;
use ByJG\RestServer\HttpResponse;
use Gateway\Gateway\GatewayPage;
use Gateway\Gateway\GatewayService;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

class TransactionController
{
    public function __construct(
        private readonly GatewayService $gateway,
        private readonly GatewayPage $page,
    ) {
    }

    /**
     * The shop registers a payment and says where to send the webhook.
     */
    #[OA\Post(path: "/transactions", tags: ["Transactions"])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["payment_id", "amount", "webhook_url"],
            properties: [
                new OA\Property(property: "payment_id", type: "integer", format: "int32"),
                new OA\Property(property: "amount", type: "number", format: "float"),
                new OA\Property(property: "webhook_url", type: "string"),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "The gateway's reference for the payment",
        content: new OA\JsonContent(
            required: ["id"],
            properties: [new OA\Property(property: "id", type: "integer", format: "int32")]
        )
    )]
    #[ValidateRequest]
    public function register(HttpResponse $response, HttpRequest $request): void
    {
        $payload = ValidateRequest::getPayload();
        $transaction = $this->gateway->register((int)$payload['payment_id'], (float)$payload['amount'], (string)$payload['webhook_url']);

        $response->write(['id' => $transaction->getId()]);
    }

    /**
     * The operator's page: every transaction, its buttons, and what the shop answered.
     */
    #[OA\Get(path: "/operator", tags: ["Page"])]
    #[OA\Response(response: 200, description: "The operator page", content: new OA\MediaType(mediaType: "text/html"))]
    public function page(HttpResponse $response, HttpRequest $request): void
    {
        $response->write($this->page->render());
    }

    /**
     * A button on the page: report an event to the shop, then go back to the page.
     */
    #[OA\Post(path: "/transactions/{id}/{action}", tags: ["Page"])]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "action", in: "path", required: true, schema: new OA\Schema(type: "string", enum: ["review", "approve", "decline", "expire", "resend"]))]
    #[OA\Response(response: 303, description: "Back to the operator page")]
    #[OA\Response(response: 404, description: "Unknown transaction", content: new OA\JsonContent(ref: "#/components/schemas/error"))]
    public function act(HttpResponse $response, HttpRequest $request): void
    {
        try {
            $this->gateway->act((int)$request->attribute('id'), (string)$request->attribute('action'));
        } catch (InvalidArgumentException $e) {
            throw new Error404Exception($e->getMessage());
        }

        $response->setResponseCode(303);
        $response->addHeader('Location', '/operator');
    }
}
