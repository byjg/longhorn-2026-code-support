<?php

namespace Shop\Command;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;
use Shop\Gateway\GatewayClient;
use Shop\Payment\Move;
use Shop\Payment\PaymentService;

/**
 * The doors that are not the webhook (slide 29), as ./gluo commands. Each one is a few
 * lines: it asks PaymentService, and prints what happened.
 */
class PaymentCommands
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly GatewayClient $gateway,
    ) {
    }

    /**
     * Create a payment and send it to the gateway: CREATED, then PENDING.
     *
     * @param float $amount
     */
    public function create(float $amount): void
    {
        $payment = $this->payments->open($amount);
        $this->say("#{$payment->id} created: CREATED");

        try {
            $reference = $this->gateway->register($payment);
        } catch (RuntimeException|ClientExceptionInterface $e) {
            $this->say("#{$payment->id} stays CREATED — the gateway did not take it: {$e->getMessage()}");
            return;
        }

        $this->report($this->payments->submit((int)$payment->id, $reference));
    }

    /**
     * The admin button: the risk team charges a payment. Only ANALYSIS -> PAID exists.
     *
     * @param int $paymentId
     */
    public function charge(int $paymentId): void
    {
        $this->report($this->payments->charge($paymentId, 'admin'));
    }

    /**
     * The retry job: a call timed out, so it charges again — the second charge of slide 12.
     *
     * @param int $paymentId
     */
    public function retry(int $paymentId): void
    {
        $this->report($this->payments->charge($paymentId, 'retry'));
    }

    /**
     * The refund endpoint: PAID -> REFUNDED, only inside the refund window.
     *
     * @param int $paymentId
     */
    public function refund(int $paymentId): void
    {
        $this->report($this->payments->refund($paymentId, $this->now()));
    }

    /**
     * The nightly sweep: fail abandoned payments, close the ones past the refund window.
     */
    public function sweep(): void
    {
        $moves = $this->payments->sweep($this->now());

        if ($moves === []) {
            $this->say('nothing to sweep');
        }

        foreach ($moves as $move) {
            $this->report($move);
        }
    }

    protected function report(Move $move): void
    {
        $this->say($move->isMoved()
            ? "#{$move->paymentId} {$move->door}: {$move->from} → {$move->to}"
            : "#{$move->paymentId} {$move->door}: {$move->from} -/-> " . ($move->to ?? '?') . " refused: {$move->reason}");
    }

    protected function say(string $line): void
    {
        echo $line . PHP_EOL;
    }

    protected function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
