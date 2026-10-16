<?php

namespace Shop\Model;

use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\Gluo\Trait\OaCreatedAt;
use ByJG\Gluo\Trait\OaUpdatedAt;
use DateTimeImmutable;
use DateTimeZone;
use OpenApi\Attributes as OA;
use Shop\Payment\PaymentState;

/**
 * The merchant's view of a payment. `status` is a Shop\Payment\PaymentState, stored as its
 * value; it is only ever written by Shop\Payment\PaymentMover, through recordMove().
 */
#[OA\Schema(required: ["id", "amount", "status"], type: "object", xml: new OA\Xml(name: "Payment"))]
#[TableAttribute("payment")]
class Payment
{
    use OaCreatedAt, OaUpdatedAt;

    #[OA\Property(type: "integer", format: "int32")]
    #[FieldAttribute(primaryKey: true, fieldName: "id")]
    public ?int $id = null;

    #[OA\Property(type: "number", format: "float")]
    #[FieldAttribute(fieldName: "amount")]
    public ?float $amount = null;

    /**
     * Stored as its value ('PAID'); a value from the database that PaymentState does not have
     * is a ValueError.
     */
    #[OA\Property(type: "string")]
    #[FieldAttribute(fieldName: "status")]
    public ?PaymentState $status = null;

    #[OA\Property(type: "string", nullable: true)]
    #[FieldAttribute(fieldName: "transition_name")]
    public ?string $transitionName = null;

    #[OA\Property(type: "string", nullable: true)]
    #[FieldAttribute(fieldName: "gateway_ref")]
    public ?string $gatewayRef = null;

    #[OA\Property(type: "string", format: "date-time", nullable: true)]
    #[FieldAttribute(fieldName: "paid_at")]
    public ?string $paidAt = null;

    /**
     * A new payment always starts as CREATED. Not saved yet: the repository does that.
     */
    public static function newPayment(float $amount): self
    {
        $payment = new self();
        $payment->amount = $amount;
        $payment->status = PaymentState::Created;

        return $payment;
    }

    /**
     * The machine moved this payment to $state. PAID also stamps paid_at, in the same write,
     * because the refund window is counted from it.
     */
    public function recordMove(string $state, ?string $transitionName): void
    {
        $this->status = PaymentState::from($state);
        $this->transitionName = $transitionName;
        if ($this->status === PaymentState::Paid) {
            $this->paidAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }
    }
}
