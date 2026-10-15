<?php

namespace Shop\Gateway;

use ByJG\Util\Uri;
use ByJG\WebRequest\Helper\RequestJson;
use Psr\Http\Client\ClientInterface;
use RuntimeException;
use Shop\Model\Payment;

/**
 * The shop's only call to the gateway: register a payment and tell it where to send the
 * webhook. Everything after that arrives through POST /webhook.
 */
class GatewayClient
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $gatewayUrl,
        private readonly string $webhookUrl,
    ) {
    }

    /**
     * @return string The gateway's reference for the payment
     */
    public function register(Payment $payment): string
    {
        $request = RequestJson::build(
            Uri::getInstanceFromString(rtrim($this->gatewayUrl, '/') . '/transactions'),
            'POST',
            [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'webhook_url' => $this->webhookUrl,
            ]
        );

        $response = $this->http->sendRequest($request);
        $body = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() !== 200 || !isset($body['id'])) {
            throw new RuntimeException("The gateway refused the payment: {$response->getStatusCode()} {$response->getBody()}");
        }

        return (string)$body['id'];
    }
}
