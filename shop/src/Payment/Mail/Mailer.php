<?php

namespace Shop\Payment\Mail;

interface Mailer
{
    public function send(PaymentConfirmed $message): void;
}
