<?php

namespace Gateway\Model;

use ByJG\Gluo\Trait\OaCreatedAt;
use ByJG\Gluo\Trait\OaUpdatedAt;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use OpenApi\Attributes as OA;

/**
 * One webhook the gateway sent, and what the shop answered.
 */
#[OA\Schema(type: "object", xml: new OA\Xml(name: "WebhookCall"))]
#[TableAttribute("webhook_call")]
class WebhookCall
{
    use OaCreatedAt, OaUpdatedAt;

    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(fieldName: "id", primaryKey: true)]
    protected int|null $id = null;

    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(fieldName: "transaction_id")]
    protected int|null $transactionId = null;

    #[OA\Property(type: "string")]
    #[FieldAttribute(fieldName: "event")]
    protected string|null $event = null;

    #[OA\Property(type: "string")]
    #[FieldAttribute(fieldName: "request_body")]
    protected string|null $requestBody = null;

    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(fieldName: "response_status")]
    protected int|null $responseStatus = null;

    #[OA\Property(type: "string")]
    #[FieldAttribute(fieldName: "response_body")]
    protected string|null $responseBody = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setId(int|null $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getTransactionId(): int|null
    {
        return $this->transactionId;
    }

    public function setTransactionId(int|null $transactionId): static
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getEvent(): string|null
    {
        return $this->event;
    }

    public function setEvent(string|null $event): static
    {
        $this->event = $event;
        return $this;
    }

    public function getRequestBody(): string|null
    {
        return $this->requestBody;
    }

    public function setRequestBody(string|null $requestBody): static
    {
        $this->requestBody = $requestBody;
        return $this;
    }

    public function getResponseStatus(): int|null
    {
        return $this->responseStatus;
    }

    public function setResponseStatus(int|null $responseStatus): static
    {
        $this->responseStatus = $responseStatus;
        return $this;
    }

    public function getResponseBody(): string|null
    {
        return $this->responseBody;
    }

    public function setResponseBody(string|null $responseBody): static
    {
        $this->responseBody = $responseBody;
        return $this;
    }
}
