<?php

namespace Gateway\Gateway;

use ByJG\Util\Uri;
use ByJG\WebRequest\Helper\RequestJson;
use Gateway\Model\Transaction;
use Gateway\Model\WebhookCall;
use Gateway\Repository\TransactionRepository;
use Gateway\Repository\WebhookCallRepository;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;

/**
 * A fake payment provider. It records payments, lets an operator decide what "happened" to
 * each one, and tells the shop through its webhook — then keeps the shop's answer.
 *
 * It knows nothing about the shop's state machine: it reports an event, and the shop
 * decides what that event means for the payment.
 */
class GatewayService
{
    /** The operator's buttons, and the event each one reports. */
    public const ACTIONS = [
        'review'  => 'requires_review',
        'approve' => 'succeeded',
        'decline' => 'declined',
        'expire'  => 'expired',
    ];

    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly WebhookCallRepository $calls,
        private readonly ClientInterface $http,
    ) {
    }

    public function register(int $paymentId, float $amount, string $webhookUrl): Transaction
    {
        $transaction = (new Transaction())
            ->setPaymentId($paymentId)
            ->setAmount($amount)
            ->setStatus('pending')
            ->setWebhookUrl($webhookUrl);
        $this->transactions->save($transaction);

        return $transaction;
    }

    /**
     * Runs an operator action — review, approve, decline, expire, or resend the last event —
     * and returns the webhook call with the shop's answer.
     */
    public function act(int $transactionId, string $action): WebhookCall
    {
        $transaction = $this->transaction($transactionId);

        if ($action === 'resend') {
            $last = $this->calls->forTransaction($transactionId)[0] ?? null;
            if ($last === null) {
                throw new InvalidArgumentException("Transaction #{$transactionId} has no event to resend");
            }
            $event = (string)$last->getEvent();
        } elseif (isset(self::ACTIONS[$action])) {
            $event = self::ACTIONS[$action];
        } else {
            throw new InvalidArgumentException("Unknown action '{$action}'. Use: " . implode(', ', [...array_keys(self::ACTIONS), 'resend']));
        }

        $transaction->setStatus($event);
        $this->transactions->save($transaction);

        return $this->notify($transaction, $event);
    }

    public function transaction(int $transactionId): Transaction
    {
        $transaction = $this->transactions->get($transactionId);
        if (!$transaction instanceof Transaction) {
            throw new InvalidArgumentException("Transaction #{$transactionId} does not exist");
        }

        return $transaction;
    }

    /**
     * POSTs the event to the shop and stores whatever came back — including "could not
     * reach the shop", recorded as status 0.
     */
    protected function notify(Transaction $transaction, string $event): WebhookCall
    {
        $payload = ['payment_id' => $transaction->getPaymentId(), 'gateway' => $event];

        try {
            $response = $this->http->sendRequest(
                RequestJson::build(Uri::getInstance((string)$transaction->getWebhookUrl()), 'POST', $payload)
            );
            $status = $response->getStatusCode();
            $body = (string)$response->getBody();
        } catch (ClientExceptionInterface $e) {
            $status = 0;
            $body = $e->getMessage();
        }

        $call = (new WebhookCall())
            ->setTransactionId($transaction->getId())
            ->setEvent($event)
            ->setRequestBody((string)json_encode($payload))
            ->setResponseStatus($status)
            ->setResponseBody($body);
        $this->calls->save($call);

        return $call;
    }
}
