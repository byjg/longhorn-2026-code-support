<?php

namespace Shop\Payment\Condition;

use ByJG\StateMachine\TransitionConditionInterface;

// PAID -> REFUNDED: a refund is only possible while the window is open.
final class InRefundWindow implements TransitionConditionInterface
{
    public function __construct(private readonly int $days = 30)
    {
    }

    #[\Override]
    public function canTransition(?array $data): bool
    {
        return isset($data['days_since_paid']) && $data['days_since_paid'] <= $this->days;
    }
}
