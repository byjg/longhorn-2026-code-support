<?php

namespace Gateway\Model;

use ByJG\Gluo\Trait\OaCreatedAt;
use ByJG\Gluo\Trait\OaUpdatedAt;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use OpenApi\Attributes as OA;

/**
 * A payment the shop registered. `status` is the last event the gateway reported.
 */
#[OA\Schema(type: "object", xml: new OA\Xml(name: "Transaction"))]
#[TableAttribute("transactions")]
class Transaction
{
    use OaCreatedAt, OaUpdatedAt;

    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(fieldName: "id", primaryKey: true)]
    protected int|null $id = null;

    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(fieldName: "payment_id")]
    protected int|null $paymentId = null;

    #[OA\Property(type: "number", format: "float")]
    #[FieldAttribute(fieldName: "amount")]
    protected float|null $amount = null;

    #[OA\Property(type: "string")]
    #[FieldAttribute(fieldName: "status")]
    protected string|null $status = null;

    #[OA\Property(type: "string")]
    #[FieldAttribute(fieldName: "webhook_url")]
    protected string|null $webhookUrl = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setId(int|null $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getPaymentId(): int|null
    {
        return $this->paymentId;
    }

    public function setPaymentId(int|null $paymentId): static
    {
        $this->paymentId = $paymentId;
        return $this;
    }

    public function getAmount(): float|null
    {
        return $this->amount;
    }

    public function setAmount(float|null $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getStatus(): string|null
    {
        return $this->status;
    }

    public function setStatus(string|null $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getWebhookUrl(): string|null
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(string|null $webhookUrl): static
    {
        $this->webhookUrl = $webhookUrl;
        return $this;
    }
}
