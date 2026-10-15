<?php

use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;
use ByJG\StateMachine\FiniteStateMachine;
use ByJG\WebRequest\HttpClient;
use Psr\Http\Client\ClientInterface;
use Shop\Command\PaymentCommands;
use Shop\Gateway\GatewayClient;
use Shop\Payment\Action\NotifyCustomer;
use Shop\Payment\Action\NotifyRiskTeam;
use Shop\Payment\Action\RefundMoney;
use Shop\Payment\Action\SendReceipt;
use Shop\Payment\Condition\InRefundWindow;
use Shop\Payment\Condition\IsAbandoned;
use Shop\Payment\Condition\PaymentAuthorized;
use Shop\Payment\Condition\RefundWindowExpired;
use Shop\Payment\Condition\RiskApproved;
use Shop\Payment\Condition\RiskRejected;
use Shop\Payment\Mail\LogMailer;
use Shop\Payment\Mail\Mailer;
use Shop\Payment\PaymentMachine;
use Shop\Payment\PaymentMover;
use Shop\Payment\PaymentService;

return [

    // Conditions — PaymentMachine names them by class; the machine asks this container for them.
    PaymentAuthorized::class => DI::bind(PaymentAuthorized::class)->withNoConstructor()->toSingleton(),
    RiskApproved::class => DI::bind(RiskApproved::class)->withNoConstructor()->toSingleton(),
    RiskRejected::class => DI::bind(RiskRejected::class)->withNoConstructor()->toSingleton(),
    IsAbandoned::class => DI::bind(IsAbandoned::class)->withConstructorArgs([48])->toSingleton(),
    InRefundWindow::class => DI::bind(InRefundWindow::class)->withConstructorArgs([30])->toSingleton(),
    RefundWindowExpired::class => DI::bind(RefundWindowExpired::class)->withConstructorArgs([30])->toSingleton(),

    // Actions — they need the payments log or a mailer, which `new $class()` could not provide.
    Mailer::class => DI::bind(LogMailer::class)->withConstructorArgs([Param::get('PaymentsLogger')])->toSingleton(),
    SendReceipt::class => DI::bind(SendReceipt::class)->withInjectedConstructor()->toSingleton(),
    NotifyRiskTeam::class => DI::bind(NotifyRiskTeam::class)->withConstructorArgs([Param::get('PaymentsLogger')])->toSingleton(),
    NotifyCustomer::class => DI::bind(NotifyCustomer::class)->withConstructorArgs([Param::get('PaymentsLogger')])->toSingleton(),
    RefundMoney::class => DI::bind(RefundMoney::class)->withConstructorArgs([Param::get('PaymentsLogger')])->toSingleton(),

    // Slide 27: the machine is built by PaymentMachine::build() with the container as resolver.
    FiniteStateMachine::class => DI::bind(PaymentMachine::class)
        ->withFactoryMethod('build', [Param::container()])
        ->toSingleton(),

    // Slide 28: the same graph read from src/Payment/payment-machine.yaml. To use it, comment
    // out the binding above and uncomment this one (and import ByJG\Serializer\Serialize).
    // FiniteStateMachine::class => function () {
    //     $yaml = file_get_contents(__DIR__ . '/../../src/Payment/payment-machine.yaml');
    //     return FiniteStateMachine::fromDefinition(
    //         Serialize::fromYaml($yaml)->toArray(),
    //         fn (string $class) => Config::get($class)
    //     );
    // },

    // Slide 32: ask, save if unchanged, then process(). The doors are in PaymentService.
    PaymentMover::class => DI::bind(PaymentMover::class)
        ->withInjectedConstructorOverrides(['logger' => Param::get('PaymentsLogger')])
        ->toSingleton(),

    PaymentService::class => DI::bind(PaymentService::class)
        ->withInjectedConstructorOverrides(['logger' => Param::get('PaymentsLogger')])
        ->toSingleton(),

    // The gateway: one HTTP call to register a payment, and the URL it should call back.
    ClientInterface::class => DI::bind(HttpClient::class)
        ->withFactoryMethod('getInstance')
        ->toSingleton(),

    GatewayClient::class => DI::bind(GatewayClient::class)
        ->withConstructorArgs([Param::get(ClientInterface::class), Param::get('GATEWAY_URL'), Param::get('SHOP_WEBHOOK_URL')])
        ->toSingleton(),

    // ./gluo payment:* — see config/commands.php
    PaymentCommands::class => DI::bind(PaymentCommands::class)
        ->withInjectedConstructor()
        ->toSingleton(),

];
