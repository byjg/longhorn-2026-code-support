<?php

namespace Tests\Payment;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Config\Config;
use Shop\Model\Payment;
use Shop\Payment\PaymentState;
use Shop\Repository\PaymentRepository;

trait PaymentFixtures
{
    /**
     * A payment already in a given state — a fixture, written straight to the table.
     */
    private function paymentIn(PaymentState $state, int $updatedHoursAgo = 0, ?int $paidDaysAgo = null): int
    {
        $payment = (new Payment())->setAmount(50.0)->setStatus($state->value);
        Config::get(PaymentRepository::class)->save($payment);
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
