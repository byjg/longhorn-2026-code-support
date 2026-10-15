<?php

namespace Shop\Payment;

use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\StateMachine\FiniteStateMachine;
use ByJG\StateMachine\State;
use ByJG\StateMachine\Transition;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Shop\Model\Payment;
use Shop\Repository\PaymentRepository;

/**
 * The one place every door goes through: the webhook, the admin button, the retry job, the
 * refund endpoint and the nightly sweep all ask this object, and this object asks the machine.
 *
 * The order is the talk's rule — check, move, then act:
 *   1. ask the machine (it never writes and never fires anything),
 *   2. save the new state, but only if the row still holds the state the move was decided from,
 *   3. call process(), so the action runs after the write survived.
 *
 * Every attempt ends as one line in the payments log, moved or refused, with the reason.
 */
class PaymentWorkflow
{
    public function __construct(
        private readonly FiniteStateMachine $machine,
        private readonly PaymentRepository $payments,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Records a new payment: CREATED. It is not submitted until submit() moves it to PENDING.
     */
    public function open(float $amount, string $door = 'create'): Payment
    {
        $payment = (new Payment())
            ->setAmount($amount)
            ->setStatus(PaymentState::Created->value);
        $this->payments->save($payment);

        $this->logger->info(sprintf('#%d %-8s %s  amount=%.2f', (int)$payment->getId(), $door, PaymentState::Created->value, $amount));

        return $payment;
    }

    /**
     * CREATED -> PENDING, once the gateway knows the payment by its reference.
     */
    public function submit(int $paymentId, string $gatewayRef, string $door = 'create'): Move
    {
        $payment = $this->load($paymentId);
        $payment->setGatewayRef($gatewayRef);
        $this->payments->save($payment);

        return $this->move($paymentId, PaymentState::Pending, ['gateway_ref' => $gatewayRef], $door);
    }

    /**
     * A door that knows where it wants to go: the admin button, the retry job, the refund.
     */
    public function move(int $paymentId, PaymentState $to, array $data, string $door): Move
    {
        $payment = $this->load($paymentId);
        $from = (string)$payment->getStatus();
        $data += ['payment' => $payment];

        $next = $this->machine->transition($from, $to, $data);

        if ($next === null) {
            return $this->refuse($payment, $door, $from, $this->whyNot($from, $to, $data), $to->value, $data);
        }

        return $this->commit($payment, $door, $from, $next, $data);
    }

    /**
     * A door that only knows what happened — the webhook. The data decides where it goes.
     */
    public function decide(int $paymentId, array $data, string $door): Move
    {
        $payment = $this->load($paymentId);
        $from = (string)$payment->getStatus();
        $data += ['payment' => $payment];

        $next = $this->machine->autoTransitionFrom($from, $data);

        if ($next === null) {
            return $this->refuse($payment, $door, $from, $this->whyNoArrow($from, $data), null, $data);
        }

        return $this->commit($payment, $door, $from, $next, $data);
    }

    /**
     * PAID -> REFUNDED, guarded by the refund window.
     */
    public function refund(int $paymentId, DateTimeImmutable $now, string $door = 'refund'): Move
    {
        $payment = $this->load($paymentId);

        return $this->move($paymentId, PaymentState::Refunded, ['days_since_paid' => $this->daysSincePaid($payment, $now)], $door);
    }

    /**
     * The nightly job: fail what was abandoned, close what can no longer be refunded.
     *
     * Explicit moves on purpose. PAID has two arrows out — REFUNDED inside the window, CLOSED
     * after it — so an automatic move from PAID would refund every recent payment.
     *
     * @return Move[]
     */
    public function sweep(DateTimeImmutable $now, string $door = 'sweep'): array
    {
        $moves = [];

        foreach ($this->payments->getByStatus(PaymentState::Pending->value) as $payment) {
            $idleHours = $this->hoursBetween((string)$payment->getUpdatedAt(), $now);
            $moves[] = $this->move((int)$payment->getId(), PaymentState::Failed, ['idle_hours' => $idleHours], $door);
        }

        foreach ($this->payments->getByStatus(PaymentState::Paid->value) as $payment) {
            $moves[] = $this->move((int)$payment->getId(), PaymentState::Closed, ['days_since_paid' => $this->daysSincePaid($payment, $now)], $door);
        }

        return $moves;
    }

    protected function load(int $paymentId): Payment
    {
        $payment = $this->payments->get($paymentId);
        if (!$payment instanceof Payment) {
            throw PaymentNotFound::withId($paymentId);
        }

        return $payment;
    }

    /**
     * Persist first, act second. If another door moved the payment since it was loaded, the
     * save is refused and the action never runs.
     */
    protected function commit(Payment $payment, string $door, string $from, State $next, array $data): Move
    {
        $to = $next->getState();

        $payment->setStatus($to);
        $payment->setTransitionName($next->getTransitionName() ?: null);
        if ($to === PaymentState::Paid->value) {
            $payment->setPaidAt((new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'));
        }

        try {
            $this->payments->saveMove($payment, $from);
        } catch (UpdateConstraintException) {
            return $this->refuse($payment, $door, $from, "lost the race: another door moved it first", $to, $data);
        }

        $this->logger->info(sprintf('#%d %-8s %s → %s%s', (int)$payment->getId(), $door, $from, $to, $this->describe($data)));

        $next->process();

        return Move::moved((int)$payment->getId(), $door, $from, $to);
    }

    protected function refuse(Payment $payment, string $door, string $from, string $reason, ?string $to, array $data): Move
    {
        $this->logger->info(sprintf(
            '#%d %-8s %s -/-> %s  refused: %s%s',
            (int)$payment->getId(), $door, $from, $to ?? '?', $reason, $this->describe($data)
        ));

        return Move::refused((int)$payment->getId(), $door, $from, $reason, $to);
    }

    /**
     * Why an explicit move was refused: there is no such arrow, or its guard said no.
     * Asking the guard again is safe: conditions are free of side effects.
     */
    protected function whyNot(string $from, PaymentState $to, array $data): string
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
    protected function whyNoArrow(string $from, array $data): string
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

    protected function daysSincePaid(Payment $payment, DateTimeImmutable $now): int
    {
        return intdiv($this->hoursBetween((string)$payment->getPaidAt(), $now), 24);
    }

    protected function hoursBetween(string $utcDateTime, DateTimeImmutable $now): int
    {
        $then = new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC'));

        return intdiv($now->getTimestamp() - $then->getTimestamp(), 3600);
    }
}
