<?php

namespace Shop\Payment\Condition;

use ByJG\StateMachine\TransitionConditionInterface;

// ANALYSIS -> PAID. Slide 24 — the rule now has a name, one home, and it guards one arrow.
final class RiskApproved implements TransitionConditionInterface
{
    #[\Override]
    public function canTransition(?array $data): bool
    {
        return ($data['gateway'] ?? null) === 'succeeded';
    }
}
