<?php

namespace Shop\Model;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\Gluo\Trait\OaCreatedAt;
use ByJG\Gluo\Trait\OaUpdatedAt;
use OpenApi\Attributes as OA;

/**
 * The merchant's view of a payment. `status` holds a case of Shop\Payment\PaymentState;
 * it is only ever written by Shop\Payment\PaymentWorkflow.
 */
#[OA\Schema(required: ["id", "amount", "status"], type: "object", xml: new OA\Xml(name: "Payment"))]
#[TableAttribute("payment")]
class Payment
{
    use OaCreatedAt, OaUpdatedAt;

    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(primaryKey: true, fieldName: "id")]
    protected int|null $id = null;

    #[OA\Property(type: "number", format: "float")]
    #[FieldAttribute(fieldName: "amount")]
    protected float|null $amount = null;

    #[OA\Property(type: "string")]
    #[FieldAttribute(fieldName: "status")]
    protected string|null $status = null;

    #[OA\Property(type: "string", nullable: true)]
    #[FieldAttribute(fieldName: "transition_name")]
    protected string|null $transitionName = null;

    #[OA\Property(type: "string", nullable: true)]
    #[FieldAttribute(fieldName: "gateway_ref")]
    protected string|null $gatewayRef = null;

    #[OA\Property(type: "string", format: "date-time", nullable: true)]
    #[FieldAttribute(fieldName: "paid_at")]
    protected string|null $paidAt = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setId(int|null $id): static
    {
        $this->id = $id;
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

    public function getTransitionName(): string|null
    {
        return $this->transitionName;
    }

    public function setTransitionName(string|null $transitionName): static
    {
        $this->transitionName = $transitionName;
        return $this;
    }

    public function getGatewayRef(): string|null
    {
        return $this->gatewayRef;
    }

    public function setGatewayRef(string|null $gatewayRef): static
    {
        $this->gatewayRef = $gatewayRef;
        return $this;
    }

    public function getPaidAt(): string|null
    {
        return $this->paidAt;
    }

    public function setPaidAt(string|null $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }
}
