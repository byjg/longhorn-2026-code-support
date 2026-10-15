<?php

namespace Gateway\Command;

use Gateway\Gateway\GatewayService;

/**
 * The page's buttons as ./gluo commands: handy in tests, and a fallback on stage.
 * Each one prints what the gateway sent and what the shop answered.
 */
class GatewayCommands
{
    public function __construct(private readonly GatewayService $gateway)
    {
    }

    /** @param int $transactionId */
    public function review(int $transactionId): void
    {
        $this->act($transactionId, 'review');
    }

    /** @param int $transactionId */
    public function approve(int $transactionId): void
    {
        $this->act($transactionId, 'approve');
    }

    /** @param int $transactionId */
    public function decline(int $transactionId): void
    {
        $this->act($transactionId, 'decline');
    }

    /** @param int $transactionId */
    public function expire(int $transactionId): void
    {
        $this->act($transactionId, 'expire');
    }

    /** @param int $transactionId */
    public function resend(int $transactionId): void
    {
        $this->act($transactionId, 'resend');
    }

    protected function act(int $transactionId, string $action): void
    {
        $call = $this->gateway->act($transactionId, $action);

        echo "→ {$call->getRequestBody()}" . PHP_EOL;
        echo "← {$call->getResponseStatus()} {$call->getResponseBody()}" . PHP_EOL;
    }
}
