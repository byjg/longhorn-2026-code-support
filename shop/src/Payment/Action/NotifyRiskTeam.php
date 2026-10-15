<?php

namespace Shop\Payment\Action;

final class NotifyRiskTeam extends LoggedAction
{
    #[\Override]
    protected function whatHappened(): string
    {
        return 'risk team notified';
    }
}
