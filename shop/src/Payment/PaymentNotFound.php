<?php

namespace Shop\Payment;

use InvalidArgumentException;

final class PaymentNotFound extends InvalidArgumentException
{
    public static function withId(int $paymentId): self
    {
        return new self("Payment #{$paymentId} does not exist");
    }
}
