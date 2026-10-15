<?php

namespace Shop\Payment\Mail;

use Shop\Model\Payment;

final class PaymentConfirmed
{
    public function __construct(public readonly Payment $payment)
    {
    }
}
