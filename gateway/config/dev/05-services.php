<?php

use ByJG\Config\DependencyInjection as DI;
use ByJG\WebRequest\HttpClient;
use Gateway\Command\GatewayCommands;
use Gateway\Gateway\GatewayPage;
use Gateway\Gateway\GatewayService;
use Psr\Http\Client\ClientInterface;

return [

    // Sends the webhooks to the shop
    ClientInterface::class => DI::bind(HttpClient::class)
        ->withFactoryMethod('getInstance')
        ->toSingleton(),

    GatewayService::class => DI::bind(GatewayService::class)
        ->withInjectedConstructor()
        ->toSingleton(),

    GatewayPage::class => DI::bind(GatewayPage::class)
        ->withInjectedConstructor()
        ->toSingleton(),

    // ./gluo gateway:* — see src/Command/Commands.php
    GatewayCommands::class => DI::bind(GatewayCommands::class)
        ->withInjectedConstructor()
        ->toSingleton(),

];
