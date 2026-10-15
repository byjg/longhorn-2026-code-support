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
 * The merchant's view of a payment. `status` holds a case of Shop\Payment\PaymentState;
 * it is only ever written by Shop\Payment\PaymentMover, through recordMove().
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
     * Stored and read as the string; written as a PaymentState case or its string value.
     * Anything else, from a door or from the database, is a ValueError. Compare it with
     * PaymentState::X->value: what you read back is never the enum.
     */
    #[OA\Property(type: "string")]
    #[FieldAttribute(fieldName: "status")]
    public ?string $status = null {
        set(string|PaymentState|null $value) {
            $this->status = match (true) {
                $value === null => null,
                $value instanceof PaymentState => $value->value,
                default => PaymentState::from($value)->value,
            };
        }
    }

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
        // Psalm 6.20 checks against the declared ?string and ignores the set hook's wider type
        /** @psalm-suppress InvalidPropertyAssignmentValue */
        $payment->status = PaymentState::Created;

        return $payment;
    }

    /**
     * The machine moved this payment to $state. PAID also stamps paid_at, in the same write,
     * because the refund window is counted from it.
     */
    public function recordMove(string $state, ?string $transitionName): void
    {
        $this->status = $state;
        $this->transitionName = $transitionName;
        if ($state === PaymentState::Paid->value) {
            $this->paidAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }
    }
}
