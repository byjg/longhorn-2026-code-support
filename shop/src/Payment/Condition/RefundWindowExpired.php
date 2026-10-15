<?php

namespace Shop\Payment\Condition;

use ByJG\StateMachine\TransitionConditionInterface;

// PAID -> CLOSED: once the refund window is over, the payment is final.
final class RefundWindowExpired implements TransitionConditionInterface
{
    public function __construct(private readonly int $days = 30)
    {
    }

    #[\Override]
    public function canTransition(?array $data): bool
    {
        return isset($data['days_since_paid']) && $data['days_since_paid'] > $this->days;
    }
}
