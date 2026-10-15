<?php

// The "before" picture: the same payment rules as shop/src/Payment, written as plain PHP
// with ifs and no state machine. It is not wired into the shop app; it is here to be read
// side by side with shop/src/Payment/PaymentMachine.php and PaymentService.php.

namespace Before;

use Psr\Log\LoggerInterface;
use Shop\Model\Payment;
use Shop\Payment\Mail\Mailer;
use Shop\Payment\Mail\PaymentConfirmed;
use Shop\Repository\PaymentRepository;

final class PaymentServiceWithIfs
{
    public function __construct(
        private PaymentRepository $repo,
        private Mailer $mailer,
        private LoggerInterface $log,
    ) {}

    // Door 1: the shop registers the payment with the gateway
    public function submit(Payment $payment): void
    {
        if ($payment->status !== 'CREATED') {
            throw new \LogicException("Cannot submit a {$payment->status} payment");
        }
        $payment->status = 'PENDING';
        $this->repo->save($payment);
    }

    // Door 2: the gateway calls the webhook
    public function handleGatewayWebhook(Payment $payment, array $data): void
    {
        $gateway = $data['gateway'] ?? null;

        if ($payment->status === 'PENDING') {
            if ($gateway === 'requires_review') {
                $payment->status = 'ANALYSIS';
                $this->repo->save($payment);
                $this->log->info("#{$payment->id} risk team notified");
            }
            // succeeded / declined while PENDING: ignored (no skipping analysis)
            return;
        }

        if ($payment->status === 'ANALYSIS') {
            if ($gateway === 'succeeded') {
                $payment->status = 'PAID';
                $this->repo->save($payment);
                $this->mailer->send(new PaymentConfirmed($payment));
            } elseif ($gateway === 'declined') {
                $payment->status = 'FAILED';
                $this->repo->save($payment);
                $this->log->info("#{$payment->id} customer told the payment failed");
            }
            // expired: nobody says yes, nothing happens
            return;
        }

        // PAID, FAILED, REFUNDED, CLOSED: a resent webhook must not charge twice
    }

    // Door 3: the admin button / retry job
    public function charge(Payment $payment): void
    {
        if ($payment->status !== 'ANALYSIS') {
            throw new \LogicException("Cannot charge a {$payment->status} payment");
        }
        $payment->status = 'PAID';
        $this->repo->save($payment);
        $this->mailer->send(new PaymentConfirmed($payment));
    }

    // Door 4: the refund
    public function refund(Payment $payment, int $daysSincePaid): void
    {
        if ($payment->status !== 'PAID') {
            throw new \LogicException("Only a PAID payment can be refunded");
        }
        if ($daysSincePaid > 30) {
            throw new \LogicException("Refund window is over");
        }
        $payment->status = 'REFUNDED';
        $this->repo->save($payment);
        $this->log->info("#{$payment->id} refund issued");
    }

    // Door 5: the nightly sweep
    public function sweep(Payment $payment, int $idleHours, ?int $daysSincePaid): void
    {
        if ($payment->status === 'PENDING' && $idleHours >= 48) {
            $payment->status = 'FAILED';
            $this->repo->save($payment);
        } elseif ($payment->status === 'PAID' && $daysSincePaid !== null && $daysSincePaid > 30) {
            $payment->status = 'CLOSED';
            $this->repo->save($payment);
        }
    }
}
