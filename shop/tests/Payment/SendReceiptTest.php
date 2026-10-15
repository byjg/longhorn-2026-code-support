<?php

namespace Tests\Payment;

use ByJG\StateMachine\FiniteStateMachine;
use ByJG\StateMachine\Transition;
use PHPUnit\Framework\TestCase;
use Shop\Model\Payment;
use Shop\Payment\Action\SendReceipt;
use Shop\Payment\Condition\RiskApproved;
use Shop\Payment\Mail\Mailer;
use Shop\Payment\Mail\PaymentConfirmed;
use Shop\Payment\PaymentState;

/**
 * An action, tested with a fake mailer and run through the machine: "persist first, act
 * second" as an executable test. Nothing is sent until the caller calls process().
 */
class SendReceiptTest extends TestCase
{
    private Mailer $mailer;
    private FiniteStateMachine $fsm;

    protected function setUp(): void
    {
        $this->mailer = new class implements Mailer {
            /** @var PaymentConfirmed[] */
            public array $sent = [];

            public function send(PaymentConfirmed $message): void
            {
                $this->sent[] = $message;
            }
        };

        $this->fsm = FiniteStateMachine::createMachine(PaymentState::class)
            ->addTransition(new Transition(
                PaymentState::Analysis, PaymentState::Paid,
                new RiskApproved(),
                new SendReceipt($this->mailer)
            ));
    }

    public function testTheReceiptGoesOutOnlyWhenTheCallerProcessesTheMove(): void
    {
        $payment = (new Payment())->setId(7);

        $paid = $this->fsm->transition(PaymentState::Analysis, PaymentState::Paid, ['gateway' => 'succeeded', 'payment' => $payment]);

        $this->assertNotNull($paid);
        $this->assertCount(0, $this->mailer->sent);     // the machine never runs the action on its own

        $paid->process();                                 // the caller does, after saving

        $this->assertCount(1, $this->mailer->sent);
        $this->assertSame($payment, $this->mailer->sent[0]->payment);
    }

    public function testARefusedMoveSendsNothing(): void
    {
        $refused = $this->fsm->transition(PaymentState::Analysis, PaymentState::Paid, ['gateway' => 'expired', 'payment' => new Payment()]);

        $this->assertNull($refused);
        $this->assertCount(0, $this->mailer->sent);
    }

    public function testTheSecondChargeHasNoArrowToTake(): void
    {
        $this->assertNull($this->fsm->transition(PaymentState::Paid, PaymentState::Paid, ['gateway' => 'succeeded', 'payment' => new Payment()]));
        $this->assertCount(0, $this->mailer->sent);
    }
}
