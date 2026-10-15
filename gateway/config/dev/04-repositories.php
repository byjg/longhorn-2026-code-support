<?php

use ByJG\Config\DependencyInjection as DI;
use Gateway\Repository\TransactionRepository;
use Gateway\Repository\WebhookCallRepository;

return [

    TransactionRepository::class => DI::bind(TransactionRepository::class)
        ->withInjectedConstructor()
        ->toSingleton(),

    WebhookCallRepository::class => DI::bind(WebhookCallRepository::class)
        ->withInjectedConstructor()
        ->toSingleton(),

];
