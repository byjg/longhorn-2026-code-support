<?php

namespace Shop\Payment\Action;

use ByJG\StateMachine\State;
use ByJG\StateMachine\TransitionActionInterface;
use InvalidArgumentException;
use Shop\Model\Payment;
use Shop\Payment\Mail\Mailer;
use Shop\Payment\Mail\PaymentConfirmed;

// Slide 25 — "Where the emails went". Runs only when the caller calls process(),
// after the move to PAID was saved.
final class SendReceipt implements TransitionActionInterface
{
    public function __construct(private readonly Mailer $mailer) {}

    #[\Override]
    public function execute(State $from, State $to, ?array $data): void
    {
        $payment = $data['payment'] ?? null;
        if (!$payment instanceof Payment) {
            throw new InvalidArgumentException('SendReceipt needs the payment in the transition data');
        }

        $this->mailer->send(new PaymentConfirmed($payment));
    }
}
