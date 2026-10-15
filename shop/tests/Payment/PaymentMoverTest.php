<?php

namespace Tests\Payment;

use ByJG\Config\Config;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\StateMachine\FiniteStateMachine;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Shop\Payment\PaymentMover;
use Shop\Payment\PaymentState;
use Shop\Repository\PaymentRepository;
use Tests\Controller\BaseApiTestCase;

/**
 * Slide 32 — the protocol every door relies on: ask, save if unchanged, then process(),
 * and name the reason when the machine says no.
 */
class PaymentMoverTest extends BaseApiTestCase
{
    use PaymentFixtures;

    private PaymentMover $mover;
    private PaymentRepository $payments;
    private TestHandler $log;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = Config::get(PaymentRepository::class);
        $this->log = new TestHandler();
        $this->mover = new PaymentMover(
            Config::get(FiniteStateMachine::class),
            $this->payments,
            new Logger('test', [$this->log])
        );
    }

    public function testAMoveIsSavedWithTheTimeItWasPaid(): void
    {
        $id = $this->paymentIn(PaymentState::Analysis);

        $move = $this->mover->move($id, PaymentState::Paid, ['gateway' => 'succeeded'], 'admin');

        $saved = $this->payments->get($id);
        $this->assertTrue($move->isMoved());
        $this->assertSame('PAID', $saved->status);
        $this->assertNotNull($saved->paidAt);
    }

    public function testTheGuardThatSaidNoIsNamed(): void
    {
        $id = $this->paymentIn(PaymentState::Analysis);

        $move = $this->mover->move($id, PaymentState::Paid, ['gateway' => 'expired'], 'admin');

        $this->assertSame('RiskApproved said no', $move->reason);
        $this->assertSame('ANALYSIS', $this->payments->get($id)->status);
    }

    public function testNoArrowLeavesAFinalState(): void
    {
        $id = $this->paymentIn(PaymentState::Closed);

        $move = $this->mover->decide($id, ['gateway' => 'succeeded'], 'webhook');

        $this->assertSame('no arrow leaves CLOSED', $move->reason);
        $this->assertTrue($this->log->hasInfoThatContains("#{$id} webhook  CLOSED -/-> ?  refused: no arrow leaves CLOSED"));
    }

    public function testASaveFromAStaleStateIsRejected(): void
    {
        $id = $this->paymentIn(PaymentState::Analysis);

        // Another door moved it to PAID after this one loaded it as ANALYSIS
        $stale = $this->payments->get($id);
        $moved = $this->payments->get($id);
        $moved->status = PaymentState::Paid;
        $this->payments->save($moved);

        $this->expectException(UpdateConstraintException::class);
        $stale->status = PaymentState::Failed;
        $this->payments->saveMove($stale, PaymentState::Analysis->value);
    }
}
