<?php

namespace Shop\Repository;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Gluo\Repository\BaseRepository;
use ByJG\MicroOrm\Constraint\CustomConstraint;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use ReflectionException;
use Shop\Model\Payment;
use Shop\Payment\PaymentNotFound;

class PaymentRepository extends BaseRepository
{
    /**
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $executor)
    {
        $this->repository = new Repository($executor, Payment::class);
    }

    public function getOrFail(int $paymentId): Payment
    {
        $payment = $this->get($paymentId);
        if (!$payment instanceof Payment) {
            throw PaymentNotFound::withId($paymentId);
        }

        return $payment;
    }

    /**
     * @return Payment[]
     */
    public function getByStatus(string $status): array
    {
        $query = Query::getInstance()
            ->table('payment')
            ->where('payment.status = :status', ['status' => $status])
            ->orderBy(['id']);

        return $this->repository->getByQuery($query);
    }

    /**
     * Saves a move only if the row still holds the state the move was decided from.
     *
     * Two doors that loaded the same payment cannot both apply a move out of that state: the
     * second save finds the status already changed and throws UpdateConstraintException.
     * micro-orm checks this by reading the row again before the update, which narrows the
     * window rather than closing it; a single `UPDATE … WHERE status = :from` would close it.
     */
    public function saveMove(Payment $payment, string $expectedStatus): void
    {
        $this->repository->save($payment, new CustomConstraint(
            fn (Payment $old): bool => $old->getStatus() === $expectedStatus,
            "Payment #{$payment->getId()} is no longer {$expectedStatus}"
        ));
    }
}
