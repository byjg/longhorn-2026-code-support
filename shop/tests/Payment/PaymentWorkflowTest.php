<?php

namespace Tests\Payment;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Config\Config;
use ByJG\MicroOrm\Exception\UpdateConstraintException;
use ByJG\StateMachine\FiniteStateMachine;
use DateTimeImmutable;
use DateTimeZone;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Shop\Model\Payment;
use Shop\Payment\PaymentNotFound;
use Shop\Payment\PaymentState;
use Shop\Payment\PaymentWorkflow;
use Shop\Repository\PaymentRepository;
use Tests\Controller\BaseApiTestCase;

/**
 * Every door goes through PaymentWorkflow. These run against the test SQLite database, with
 * the machine and the repository from the container; only the workflow's own log is captured.
 */
class PaymentWorkflowTest extends BaseApiTestCase
{
    private PaymentWorkflow $workflow;
    private PaymentRepository $payments;
    private TestHandler $log;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = Config::get(PaymentRepository::class);
        $this->log = new TestHandler();
        $this->workflow = new PaymentWorkflow(
            Config::get(FiniteStateMachine::class),
            $this->payments,
            new Logger('test', [$this->log])
        );
    }

    public function testThePaymentWalksTheHappyPath(): void
    {
        $payment = $this->workflow->open(100.0);
        $this->assertSame('CREATED', $payment->getStatus());

        $created = $this->workflow->submit((int)$payment->getId(), 'GW-1');
        $this->assertTrue($created->isMoved());
        $this->assertSame(['CREATED', 'PENDING'], [$created->from, $created->to]);
        $this->assertSame('GW-1', $this->payments->get($created->paymentId)->getGatewayRef());

        $review = $this->workflow->decide($created->paymentId, ['gateway' => 'requires_review'], 'webhook');
        $this->assertSame('ANALYSIS', $review->to);

        $paid = $this->workflow->decide($created->paymentId, ['gateway' => 'succeeded'], 'webhook');
        $this->assertSame('PAID', $paid->to);

        $saved = $this->payments->get($created->paymentId);
        $this->assertSame('PAID', $saved->getStatus());
        $this->assertNotNull($saved->getPaidAt());
        $this->assertTrue($this->log->hasInfoThatContains("#{$created->paymentId} webhook  ANALYSIS → PAID  gateway=succeeded"));
    }

    public function testAnUnknownPaymentIsReported(): void
    {
        $this->expectException(PaymentNotFound::class);
        $this->workflow->decide(999999, ['gateway' => 'succeeded'], 'webhook');
    }

    // Slide 26 — "A second charge asks for PAID → PAID. That arrow does not exist."
    public function testTheSecondChargeIsRefused(): void
    {
        $id = $this->paymentIn(PaymentState::Paid);

        $move = $this->workflow->move($id, PaymentState::Paid, ['gateway' => 'succeeded'], 'admin');

        $this->assertFalse($move->isMoved());
        $this->assertSame('no arrow PAID → PAID', $move->reason);
        $this->assertSame('PAID', $this->payments->get($id)->getStatus());
        $this->assertTrue($this->log->hasInfoThatContains("#{$id} admin    PAID -/-> PAID  refused: no arrow PAID → PAID"));
    }

    public function testApprovingAPendingPaymentCannotSkipAnalysis(): void
    {
        $id = $this->paymentIn(PaymentState::Pending);

        $move = $this->workflow->decide($id, ['gateway' => 'succeeded'], 'webhook');

        $this->assertFalse($move->isMoved());
        $this->assertSame('PENDING→ANALYSIS (PaymentAuthorized: no), PENDING→FAILED (IsAbandoned: no)', $move->reason);
        $this->assertSame('PENDING', $this->payments->get($id)->getStatus());
    }

    public function testTheGuardThatSaidNoIsNamed(): void
    {
        $id = $this->paymentIn(PaymentState::Analysis);

        $move = $this->workflow->move($id, PaymentState::Paid, ['gateway' => 'expired'], 'admin');

        $this->assertSame('RiskApproved said no', $move->reason);
        $this->assertSame('ANALYSIS', $this->payments->get($id)->getStatus());
    }

    public function testASaveFromAStaleStateIsRejected(): void
    {
        $id = $this->paymentIn(PaymentState::Analysis);

        // Another door moved it to PAID after this one loaded it as ANALYSIS
        $stale = $this->payments->get($id);
        $this->payments->save($this->payments->get($id)->setStatus(PaymentState::Paid->value));

        $this->expectException(UpdateConstraintException::class);
        $this->payments->saveMove($stale->setStatus(PaymentState::Failed->value), PaymentState::Analysis->value);
    }

    public function testTheSweepFailsAbandonedPaymentsAndClosesExpiredOnes(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $abandoned = $this->paymentIn(PaymentState::Pending, updatedHoursAgo: 72);
        $fresh = $this->paymentIn(PaymentState::Pending, updatedHoursAgo: 1);
        $expired = $this->paymentIn(PaymentState::Paid, paidDaysAgo: 40);
        $refundable = $this->paymentIn(PaymentState::Paid, paidDaysAgo: 3);

        $moves = [];
        foreach ($this->workflow->sweep($now) as $move) {
            $moves[$move->paymentId] = $move;
        }

        $this->assertSame('FAILED', $moves[$abandoned]->to);
        $this->assertSame('IsAbandoned said no', $moves[$fresh]->reason);
        $this->assertSame('CLOSED', $moves[$expired]->to);
        $this->assertSame('RefundWindowExpired said no', $moves[$refundable]->reason);
        $this->assertSame('PAID', $this->payments->get($refundable)->getStatus());
    }

    public function testARefundOnlyInsideTheWindow(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $recent = $this->paymentIn(PaymentState::Paid, paidDaysAgo: 3);
        $old = $this->paymentIn(PaymentState::Paid, paidDaysAgo: 45);

        $this->assertSame('REFUNDED', $this->workflow->refund($recent, $now)->to);
        $this->assertSame('InRefundWindow said no', $this->workflow->refund($old, $now)->reason);
    }

    /**
     * A payment already in a given state — a fixture, written straight to the table.
     */
    private function paymentIn(PaymentState $state, int $updatedHoursAgo = 0, ?int $paidDaysAgo = null): int
    {
        $payment = (new Payment())->setAmount(50.0)->setStatus($state->value);
        $this->payments->save($payment);
        $id = (int)$payment->getId();

        /** @var DatabaseExecutor $executor */
        $executor = Config::get(DatabaseExecutor::class);
        $executor->execute(
            "update payment set updated_at = datetime('now', :updated), paid_at = :paid where id = :id",
            [
                'updated' => "-{$updatedHoursAgo} hours",
                'paid' => $paidDaysAgo === null ? null : gmdate('Y-m-d H:i:s', time() - $paidDaysAgo * 86400),
                'id' => $id,
            ]
        );

        return $id;
    }
}
