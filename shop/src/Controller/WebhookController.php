<?php

namespace Shop\Controller;

use ByJG\Gluo\Attribute\ValidateRequest;
use ByJG\RestServer\Exception\Error404Exception;
use ByJG\RestServer\HttpRequest;
use ByJG\RestServer\HttpResponse;
use OpenApi\Attributes as OA;
use Shop\Payment\PaymentNotFound;
use Shop\Payment\PaymentService;

/**
 * Slide 37 — "What that leaves in your controller". The gateway only knows what happened;
 * the machine decides where that takes the payment. No branches left to test here.
 */
class WebhookController
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    #[OA\Post(path: "/webhook", tags: ["Webhook"])]
    #[OA\RequestBody(
        description: "What the gateway saw happen to a payment",
        required: true,
        content: new OA\JsonContent(
            required: ["payment_id", "gateway"],
            properties: [
                new OA\Property(property: "payment_id", type: "integer", format: "int32"),
                new OA\Property(property: "gateway", type: "string", enum: ["requires_review", "succeeded", "declined", "expired"]),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Where the payment went, or why it stayed",
        content: new OA\JsonContent(
            required: ["payment_id", "from"],
            properties: [
                new OA\Property(property: "payment_id", type: "integer", format: "int32"),
                new OA\Property(property: "from", type: "string"),
                new OA\Property(property: "to", type: "string"),
                new OA\Property(property: "result", type: "string"),
                new OA\Property(property: "reason", type: "string"),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "Unknown payment",
        content: new OA\JsonContent(ref: "#/components/schemas/error")
    )]
    #[ValidateRequest]
    public function receive(HttpResponse $response, HttpRequest $request): void
    {
        $payload = ValidateRequest::getPayload();

        try {
            $move = $this->payments->gatewayReported((int)$payload['payment_id'], (string)$payload['gateway']);
        } catch (PaymentNotFound $e) {
            throw new Error404Exception($e->getMessage());
        }

        $response->write($move->toArray());
    }
}
