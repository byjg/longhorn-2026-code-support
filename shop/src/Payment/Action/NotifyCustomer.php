<?php

namespace Shop\Payment\Action;

final class NotifyCustomer extends LoggedAction
{
    #[\Override]
    protected function whatHappened(): string
    {
        return 'customer told the payment failed';
    }
}
