<?php

namespace Shop\Payment\Action;

use ByJG\StateMachine\State;
use ByJG\StateMachine\TransitionActionInterface;
use Psr\Log\LoggerInterface;
use Shop\Model\Payment;

/**
 * The demo's side effects are log lines. Each action says what it would have done; the
 * line carries the payment, so the log reads as one story per payment.
 */
abstract class LoggedAction implements TransitionActionInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    abstract protected function whatHappened(): string;

    #[\Override]
    public function execute(State $from, State $to, ?array $data): void
    {
        $payment = $data['payment'] ?? null;
        $id = $payment instanceof Payment ? (int)$payment->getId() : 0;

        $name = (new \ReflectionClass($this))->getShortName();
        $this->logger->info(sprintf('#%d action   %s: %s', $id, $name, $this->whatHappened()));
    }
}
