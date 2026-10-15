<?php

namespace Shop\Payment\Action;

final class RefundMoney extends LoggedAction
{
    #[\Override]
    protected function whatHappened(): string
    {
        return 'refund issued';
    }
}
