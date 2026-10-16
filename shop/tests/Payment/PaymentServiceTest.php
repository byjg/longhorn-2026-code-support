<?php

namespace Tests\Payment;

use ByJG\Config\Config;
use ByJG\StateMachine\FiniteStateMachine;
use DateTimeImmutable;
use DateTimeZone;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Shop\Payment\PaymentMover;
use Shop\Payment\PaymentNotFound;
use Shop\Payment\PaymentService;
use Shop\Payment\PaymentState;
use Shop\Repository\PaymentRepository;
use Tests\Controller\BaseApiTestCase;

/**
 * Every door goes through PaymentService. These run against the test SQLite database, with
 * the machine and the repository from the container; only the payments log is captured.
 */
class PaymentServiceTest extends BaseApiTestCase
{
    use PaymentFixtures;

    private PaymentService $service;
    private PaymentRepository $payments;
    private TestHandler $log;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = Config::get(PaymentRepository::class);
        $this->log = new TestHandler();
        $logger = new Logger('test', [$this->log]);
        $this->service = new PaymentService(
            new PaymentMover(Config::get(FiniteStateMachine::class), $this->payments, $logger),
            $this->payments,
            $logger
        );
    }

    public function testThePaymentWalksTheHappyPath(): void
    {
        $payment = $this->service->open(100.0);
        $this->assertSame(PaymentState::Created, $payment->status);

        $created = $this->service->submit((int)$payment->id, 'GW-1');
        $this->assertTrue($created->isMoved());
        $this->assertSame(['CREATED', 'PENDING'], [$created->from, $created->to]);
        $this->assertSame('GW-1', $this->payments->get($created->paymentId)->gatewayRef);

        $review = $this->service->gatewayReported($created->paymentId, 'requires_review');
        $this->assertSame('ANALYSIS', $review->to);

        $paid = $this->service->gatewayReported($created->paymentId, 'succeeded');
        $this->assertSame('PAID', $paid->to);

        $saved = $this->payments->get($created->paymentId);
        $this->assertSame(PaymentState::Paid, $saved->status);
        $this->assertNotNull($saved->paidAt);
        $this->assertTrue($this->log->hasInfoThatContains("#{$created->paymentId} webhook  ANALYSIS → PAID  gateway=succeeded"));
    }

    public function testAnUnknownPaymentIsReported(): void
    {
        $this->expectException(PaymentNotFound::class);
        $this->service->gatewayReported(999999, 'succeeded');
    }

    // Slide 27 — "A second charge asks for PAID → PAID. That arrow does not exist."
    public function testTheSecondChargeIsRefused(): void
    {
        $id = $this->paymentIn(PaymentState::Paid);

        $move = $this->service->charge($id);

        $this->assertFalse($move->isMoved());
        $this->assertSame('no arrow PAID → PAID', $move->reason);
        $this->assertSame(PaymentState::Paid, $this->payments->get($id)->status);
        $this->assertTrue($this->log->hasInfoThatContains("#{$id} admin    PAID -/-> PAID  refused: no arrow PAID → PAID"));
    }

    public function testApprovingAPendingPaymentCannotSkipAnalysis(): void
    {
        $id = $this->paymentIn(PaymentState::Pending);

        $move = $this->service->gatewayReported($id, 'succeeded');

        $this->assertFalse($move->isMoved());
        $this->assertSame('PENDING→ANALYSIS (PaymentAuthorized: no), PENDING→FAILED (IsAbandoned: no)', $move->reason);
        $this->assertSame(PaymentState::Pending, $this->payments->get($id)->status);
    }

    public function testTheSweepFailsAbandonedPaymentsAndClosesExpiredOnes(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $abandoned = $this->paymentIn(PaymentState::Pending, updatedHoursAgo: 72);
        $fresh = $this->paymentIn(PaymentState::Pending, updatedHoursAgo: 1);
        $expired = $this->paymentIn(PaymentState::Paid, paidDaysAgo: 40);
        $refundable = $this->paymentIn(PaymentState::Paid, paidDaysAgo: 3);

        $moves = [];
        foreach ($this->service->sweep($now) as $move) {
            $moves[$move->paymentId] = $move;
        }

        $this->assertSame('FAILED', $moves[$abandoned]->to);
        $this->assertSame('IsAbandoned said no', $moves[$fresh]->reason);
        $this->assertSame('CLOSED', $moves[$expired]->to);
        $this->assertSame('RefundWindowExpired said no', $moves[$refundable]->reason);
        $this->assertSame(PaymentState::Paid, $this->payments->get($refundable)->status);
    }

    public function testARefundOnlyInsideTheWindow(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $recent = $this->paymentIn(PaymentState::Paid, paidDaysAgo: 3);
        $old = $this->paymentIn(PaymentState::Paid, paidDaysAgo: 45);

        $this->assertSame('REFUNDED', $this->service->refund($recent, $now)->to);
        $this->assertSame('InRefundWindow said no', $this->service->refund($old, $now)->reason);
    }
}
