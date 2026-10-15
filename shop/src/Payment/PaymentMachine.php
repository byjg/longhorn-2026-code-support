<?php

namespace Shop\Payment;

use ByJG\StateMachine\FiniteStateMachine;
use Psr\Container\ContainerInterface;
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

// Slide 27 — "One file. The graph, the guards and the effects."
final class PaymentMachine
{
    public static function build(ContainerInterface $c): FiniteStateMachine
    {
        return FiniteStateMachine::createMachine(PaymentState::class, [
            [PaymentState::Created,  PaymentState::Pending,  null,                        null],
            [PaymentState::Pending,  PaymentState::Analysis, PaymentAuthorized::class,    NotifyRiskTeam::class],
            [PaymentState::Pending,  PaymentState::Failed,   IsAbandoned::class,          null],
            [PaymentState::Analysis, PaymentState::Paid,     RiskApproved::class,         SendReceipt::class],
            [PaymentState::Analysis, PaymentState::Failed,   RiskRejected::class,         NotifyCustomer::class],
            [PaymentState::Paid,     PaymentState::Refunded, InRefundWindow::class,       RefundMoney::class],
            [PaymentState::Paid,     PaymentState::Closed,   RefundWindowExpired::class,  null],
        ], [$c, 'get']);
    }
}
