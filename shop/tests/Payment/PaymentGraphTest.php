<?php

namespace Tests\Payment;

use ByJG\Config\Config;
use ByJG\StateMachine\FiniteStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
use Shop\Payment\PaymentState;

/**
 * Slides 34 and 35 — the graph is an executable spec, and the guards ride on it.
 *
 * The machine comes from the container, exactly as the app builds it, so these tests also
 * prove the wiring: PaymentMachine::build() with the container as the resolver.
 */
class PaymentGraphTest extends TestCase
{
    private FiniteStateMachine $fsm;

    protected function setUp(): void
    {
        $this->fsm = Config::get(FiniteStateMachine::class);
    }

    // Slide 34: does the arrow exist? No guard runs, no data needed.
    public static function moves(): array
    {
        return [
            [PaymentState::Analysis, PaymentState::Paid,     true ],
            [PaymentState::Paid,     PaymentState::Paid,     false],   // the second charge
            [PaymentState::Pending,  PaymentState::Paid,     false],   // skipping analysis
            [PaymentState::Closed,   PaymentState::Refunded, false],   // nothing leaves CLOSED
        ];
    }

    #[DataProvider('moves')]
    public function testTheGraph(PaymentState $from, PaymentState $to, bool $exists): void
    {
        $this->assertSame($exists, $this->fsm->getTransition($from, $to) !== null);
    }

    // Slide 35: the same table, one more column — the data that reaches each arrow.
    public static function decisions(): array
    {
        return [
            [PaymentState::Analysis, PaymentState::Paid,     ['gateway' => 'succeeded'], true ],
            [PaymentState::Analysis, PaymentState::Paid,     ['gateway' => 'expired'],   false],  // nobody says yes
            [PaymentState::Paid,     PaymentState::Refunded, ['days_since_paid' => 3],   true ],
            [PaymentState::Paid,     PaymentState::Refunded, ['days_since_paid' => 45],  false],  // window is over
        ];
    }

    #[DataProvider('decisions')]
    public function testTheRules(PaymentState $from, PaymentState $to, array $data, bool $allowed): void
    {
        $this->assertSame($allowed, $this->fsm->canTransition($from, $to, $data));
    }

    public function testNothingLeavesTheFinalStates(): void
    {
        foreach ([PaymentState::Failed, PaymentState::Refunded, PaymentState::Closed] as $final) {
            $this->assertTrue($this->fsm->isFinalState($final), "{$final->value} should be final");
        }
        $this->assertTrue($this->fsm->isInitialState(PaymentState::Created));
    }

    // The wiring check: each arrow carries the guard and the action slide 27 names.
    public static function wiring(): array
    {
        return [
            [PaymentState::Created,  PaymentState::Pending,  null,                       null],
            [PaymentState::Pending,  PaymentState::Analysis, PaymentAuthorized::class,   NotifyRiskTeam::class],
            [PaymentState::Pending,  PaymentState::Failed,   IsAbandoned::class,         null],
            [PaymentState::Analysis, PaymentState::Paid,     RiskApproved::class,        SendReceipt::class],
            [PaymentState::Analysis, PaymentState::Failed,   RiskRejected::class,        NotifyCustomer::class],
            [PaymentState::Paid,     PaymentState::Refunded, InRefundWindow::class,      RefundMoney::class],
            [PaymentState::Paid,     PaymentState::Closed,   RefundWindowExpired::class, null],
        ];
    }

    #[DataProvider('wiring')]
    public function testEachArrowCarriesItsGuardAndAction(PaymentState $from, PaymentState $to, ?string $guard, ?string $action): void
    {
        $transition = $this->fsm->getTransition($from, $to);

        $this->assertNotNull($transition);
        $this->assertSame($guard, $transition->getTransitionCondition() === null ? null : get_class($transition->getTransitionCondition()));
        $this->assertSame($action, $transition->getTransitionAction() === null ? null : get_class($transition->getTransitionAction()));
    }
}
