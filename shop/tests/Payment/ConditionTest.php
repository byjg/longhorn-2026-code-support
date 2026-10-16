<?php

namespace Tests\Payment;

use ByJG\StateMachine\TransitionConditionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shop\Payment\Condition\InRefundWindow;
use Shop\Payment\Condition\IsAbandoned;
use Shop\Payment\Condition\PaymentAuthorized;
use Shop\Payment\Condition\RefundWindowExpired;
use Shop\Payment\Condition\RiskApproved;
use Shop\Payment\Condition\RiskRejected;

/**
 * Slide 37 — "A condition, tested with nothing". No container, no database, no HTTP,
 * no frozen clock: a condition only reads the data it is handed.
 */
class ConditionTest extends TestCase
{
    public function testAbandonedAfterFortyEightHours(): void
    {
        $rule = new IsAbandoned(afterHours: 48);

        $this->assertTrue($rule->canTransition(['idle_hours' => 72]));
        $this->assertFalse($rule->canTransition(['idle_hours' => 12]));
    }

    public function testRiskApprovedOnlyWhenTheGatewaySucceeded(): void
    {
        $rule = new RiskApproved();

        $this->assertTrue($rule->canTransition(['gateway' => 'succeeded']));
        $this->assertFalse($rule->canTransition(['gateway' => 'expired']));
        $this->assertFalse($rule->canTransition(null));
    }

    public static function rules(): array
    {
        return [
            'authorized: review'        => [new PaymentAuthorized(), ['gateway' => 'requires_review'], true],
            'authorized: succeeded'     => [new PaymentAuthorized(), ['gateway' => 'succeeded'], false],
            'rejected: declined'        => [new RiskRejected(), ['gateway' => 'declined'], true],
            'rejected: succeeded'       => [new RiskRejected(), ['gateway' => 'succeeded'], false],
            'abandoned: exactly 48h'    => [new IsAbandoned(48), ['idle_hours' => 48], true],
            'abandoned: no idle time'   => [new IsAbandoned(48), [], false],
            'refund: day 30'            => [new InRefundWindow(30), ['days_since_paid' => 30], true],
            'refund: day 31'            => [new InRefundWindow(30), ['days_since_paid' => 31], false],
            'expired: day 30'           => [new RefundWindowExpired(30), ['days_since_paid' => 30], false],
            'expired: day 31'           => [new RefundWindowExpired(30), ['days_since_paid' => 31], true],
            'expired: never paid'       => [new RefundWindowExpired(30), [], false],
        ];
    }

    #[DataProvider('rules')]
    public function testEachRuleOnItsOwn(TransitionConditionInterface $rule, array $data, bool $expected): void
    {
        $this->assertSame($expected, $rule->canTransition($data));
    }
}
