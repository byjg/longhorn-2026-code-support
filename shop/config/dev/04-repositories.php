<?php

use ByJG\Config\DependencyInjection as DI;
use Shop\Repository\PaymentRepository;

return [

    PaymentRepository::class => DI::bind(PaymentRepository::class)
        ->withInjectedConstructor()
        ->toSingleton(),

];
