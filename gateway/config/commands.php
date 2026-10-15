<?php

/**
 * Project commands: ./gluo <name> <transaction id>. The same actions as the buttons on the
 * operator page (/operator).
 *
 * Every public method of GatewayCommands is a command: gateway:review, gateway:approve,
 * gateway:decline, gateway:expire, gateway:resend.
 */
return [
    'gateway' => 'Gateway/Command/GatewayCommands',
];
