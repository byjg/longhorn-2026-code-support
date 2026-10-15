<?php

namespace Shop\Payment;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Shop\Model\Payment;
use Shop\Repository\PaymentRepository;

/**
 * The doors (slide 29): the webhook, the admin button, the retry job, the refund and the
 * nightly sweep. Each turns what it knows into the data the guards read, and hands the move
 * to PaymentMover. The controller and the commands only talk to this class.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentMover $mover,
        private readonly PaymentRepository $payments,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Records a new payment: CREATED. It is not submitted until submit() moves it to PENDING.
     */
    public function open(float $amount, string $door = 'create'): Payment
    {
        $payment = Payment::newPayment($amount);
        $this->payments->save($payment);

        $this->logger->info(sprintf('#%d %-8s %s  amount=%.2f', (int)$payment->getId(), $door, PaymentState::Created->value, $amount));

        return $payment;
    }

    /**
     * CREATED -> PENDING, once the gateway knows the payment by its reference.
     */
    public function submit(int $paymentId, string $gatewayRef, string $door = 'create'): Move
    {
        $payment = $this->payments->getOrFail($paymentId);
        $payment->setGatewayRef($gatewayRef);
        $this->payments->save($payment);

        return $this->mover->move($paymentId, PaymentState::Pending, ['gateway_ref' => $gatewayRef], $door);
    }

    /**
     * The webhook: the gateway reports what happened, and the data decides where it goes.
     */
    public function gatewayReported(int $paymentId, string $status, string $door = 'webhook'): Move
    {
        return $this->mover->decide($paymentId, ['gateway' => $status], $door);
    }

    /**
     * The admin button and the retry job: charge it. Only ANALYSIS -> PAID exists, so a second
     * charge is refused (slide 26).
     */
    public function charge(int $paymentId, string $door = 'admin'): Move
    {
        return $this->mover->move($paymentId, PaymentState::Paid, ['gateway' => 'succeeded'], $door);
    }

    /**
     * PAID -> REFUNDED, guarded by the refund window.
     */
    public function refund(int $paymentId, DateTimeImmutable $now, string $door = 'refund'): Move
    {
        $payment = $this->payments->getOrFail($paymentId);

        return $this->mover->move($paymentId, PaymentState::Refunded, ['days_since_paid' => $this->daysSincePaid($payment, $now)], $door);
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
            $moves[] = $this->mover->move((int)$payment->getId(), PaymentState::Failed, ['idle_hours' => $idleHours], $door);
        }

        foreach ($this->payments->getByStatus(PaymentState::Paid->value) as $payment) {
            $moves[] = $this->mover->move((int)$payment->getId(), PaymentState::Closed, ['days_since_paid' => $this->daysSincePaid($payment, $now)], $door);
        }

        return $moves;
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
