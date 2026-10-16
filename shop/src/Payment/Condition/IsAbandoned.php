<?php

namespace Shop\Payment\Condition;

use ByJG\StateMachine\TransitionConditionInterface;

// PENDING -> FAILED. Reads the idle time from the data instead of a clock, so slide 37
// tests it with nothing: no container, no database, no frozen clock.
final class IsAbandoned implements TransitionConditionInterface
{
    public function __construct(private readonly int $afterHours = 48)
    {
    }

    #[\Override]
    public function canTransition(?array $data): bool
    {
        return isset($data['idle_hours']) && $data['idle_hours'] >= $this->afterHours;
    }
}
