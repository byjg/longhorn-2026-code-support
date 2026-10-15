<?php

namespace Shop\Payment\Mail;

use Psr\Log\LoggerInterface;

// The demo sends no real email: a "sent" receipt is a line in the payments log.
final class LogMailer implements Mailer
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    #[\Override]
    public function send(PaymentConfirmed $message): void
    {
        $this->logger->info(sprintf('#%d mail     PaymentConfirmed: receipt sent', (int)$message->payment->getId()));
    }
}
