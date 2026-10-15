<?php

namespace Shop\Payment\Condition;

use ByJG\StateMachine\TransitionConditionInterface;

// PENDING -> ANALYSIS: the gateway wants a human to look at it.
final class PaymentAuthorized implements TransitionConditionInterface
{
    #[\Override]
    public function canTransition(?array $data): bool
    {
        return ($data['gateway'] ?? null) === 'requires_review';
    }
}
