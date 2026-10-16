<?php

namespace Shop\Payment;

use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\StateMachine\FiniteStateMachine;
use ByJG\StateMachine\State;
use ByJG\StateMachine\Transition;
use Psr\Log\LoggerInterface;
use Shop\Model\Payment;
use Shop\Repository\PaymentRepository;

/**
 * Slide 32 — "The machine stops at step 2. Step 3 is yours to call." This is step 3, the same
 * for every move and knowing nothing about why a door asked:
 *   1. ask the machine (it never writes and never fires anything),
 *   2. save the new state, but only if the row still holds the state the move was decided from,
 *   3. call process(), so the action runs after the write survived.
 *
 * Every attempt ends as one line in the payments log, moved or refused, with the reason.
 * The doors, and the data they bring, are in PaymentService.
 */
class PaymentMover
{
    public function __construct(
        private readonly FiniteStateMachine $machine,
        private readonly PaymentRepository $payments,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * The door knows where it wants to go; the guard on that one arrow decides.
     */
    public function move(int $paymentId, PaymentState $to, array $data, string $door): Move
    {
        $payment = $this->payments->getOrFail($paymentId);
        $from = (string)$payment->status?->value;
        $data += ['payment' => $payment];

        $next = $this->machine->transition($from, $to, $data);

        if ($next === null) {
            return $this->refuse($payment, $door, $from, $this->whyNot($from, $to), $to->value, $data);
        }

        return $this->commit($payment, $door, $from, $next, $data);
    }

    /**
     * The door only knows what happened (slide 30); the data decides where it goes.
     */
    public function decide(int $paymentId, array $data, string $door): Move
    {
        $payment = $this->payments->getOrFail($paymentId);
        $from = (string)$payment->status?->value;
        $data += ['payment' => $payment];

        $next = $this->machine->autoTransitionFrom($from, $data);

        if ($next === null) {
            return $this->refuse($payment, $door, $from, $this->whyNoArrow($from), null, $data);
        }

        return $this->commit($payment, $door, $from, $next, $data);
    }

    /**
     * Persist first, act second. If another door moved the payment since it was loaded, the
     * save is refused and the action never runs.
     */
    protected function commit(Payment $payment, string $door, string $from, State $next, array $data): Move
    {
        $to = $next->getState();
        $payment->recordMove($to, $next->getTransitionName() ?: null);

        try {
            $this->payments->saveMove($payment, $from);
        } catch (UpdateConstraintException) {
            return $this->refuse($payment, $door, $from, "lost the race: another door moved it first", $to, $data);
        }

        $this->logger->info(sprintf('#%d %-8s %s → %s%s', (int)$payment->id, $door, $from, $to, $this->describe($data)));

        $next->process();

        return Move::moved((int)$payment->id, $door, $from, $to);
    }

    protected function refuse(Payment $payment, string $door, string $from, string $reason, ?string $to, array $data): Move
    {
        $this->logger->info(sprintf(
            '#%d %-8s %s -/-> %s  refused: %s%s',
            (int)$payment->id, $door, $from, $to ?? '?', $reason, $this->describe($data)
        ));

        return Move::refused((int)$payment->id, $door, $from, $reason, $to);
    }

    /**
     * Why an explicit move was refused: there is no such arrow, or its guard said no.
     */
    protected function whyNot(string $from, PaymentState $to): string
    {
        $transition = $this->machine->getTransition($from, $to);

        if ($transition === null) {
            return "no arrow {$from} → {$to->value}";
        }

        return $this->guardName($transition) . ' said no';
    }

    /**
     * Why the data moved the payment nowhere: every arrow leaving it, and the guard that said no.
     */
    protected function whyNoArrow(string $from): string
    {
        $leaving = $this->machine->possibleTransitions($from);

        if ($leaving === []) {
            return "no arrow leaves {$from}";
        }

        return implode(', ', array_map(
            fn (Transition $t): string => "{$from}→{$t->getDesiredState()} ({$this->guardName($t)}: no)",
            $leaving
        ));
    }

    protected function guardName(Transition $transition): string
    {
        $condition = $transition->getTransitionCondition();

        return $condition === null ? 'no guard' : (new \ReflectionClass($condition))->getShortName();
    }

    /**
     * The data a door brought, without the payment itself: "gateway=succeeded".
     */
    protected function describe(array $data): string
    {
        $pairs = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $pairs[] = "{$key}={$value}";
            }
        }

        return $pairs === [] ? '' : '  ' . implode(' ', $pairs);
    }
}
