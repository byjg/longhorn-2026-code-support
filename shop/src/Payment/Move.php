<?php

namespace Shop\Payment;

/**
 * What happened when a door asked to move a payment: where it was, where it went, or why
 * it stayed. This is also the webhook's answer to the gateway.
 */
final class Move
{
    private function __construct(
        public readonly int $paymentId,
        public readonly string $door,
        public readonly string $from,
        public readonly ?string $to,
        public readonly ?string $reason,
    ) {
    }

    public static function moved(int $paymentId, string $door, string $from, string $to): self
    {
        return new self($paymentId, $door, $from, $to, null);
    }

    public static function refused(int $paymentId, string $door, string $from, string $reason, ?string $to = null): self
    {
        return new self($paymentId, $door, $from, $to, $reason);
    }

    public function isMoved(): bool
    {
        return $this->reason === null;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return $this->isMoved()
            ? ['payment_id' => $this->paymentId, 'from' => $this->from, 'to' => $this->to]
            : ['payment_id' => $this->paymentId, 'from' => $this->from, 'result' => 'refused', 'reason' => $this->reason];
    }
}
