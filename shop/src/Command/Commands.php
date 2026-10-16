<?php

namespace Shop\Command;

use ByJG\Scriptify\CommandProvider;

/**
 * Project commands: ./gluo <name> [args]. Each is a door into PaymentService (slide 30).
 * The webhook is the fifth door, POST /webhook.
 *
 * Every public method of PaymentCommands is a command: payment:create, payment:charge,
 * payment:retry, payment:refund, payment:sweep.
 */
class Commands implements CommandProvider
{
    #[\Override]
    public function commands(): array
    {
        return [
            'payment' => PaymentCommands::class,
        ];
    }
}
