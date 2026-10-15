<?php

/**
 * Project commands: ./gluo <name> [args]. Each is a door into PaymentService (slide 29).
 * The webhook is the fifth door, POST /webhook.
 *
 * Every public method of PaymentCommands is a command: payment:create, payment:charge,
 * payment:retry, payment:refund, payment:sweep.
 */
return [
    'payment' => 'Shop/Command/PaymentCommands',
];
