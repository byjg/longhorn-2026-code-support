<?php

namespace Shop\Payment\Condition;

use ByJG\StateMachine\TransitionConditionInterface;

// ANALYSIS -> FAILED: the gateway (or the risk team) said no.
final class RiskRejected implements TransitionConditionInterface
{
    #[\Override]
    public function canTransition(?array $data): bool
    {
        return ($data['gateway'] ?? null) === 'declined';
    }
}
