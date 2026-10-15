<?php

namespace Gateway\Repository;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Gluo\Repository\BaseRepository;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use Gateway\Model\Transaction;
use ReflectionException;

class TransactionRepository extends BaseRepository
{
    /**
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $executor)
    {
        $this->repository = new Repository($executor, Transaction::class);
    }

    /**
     * @return Transaction[] Newest first
     */
    public function newestFirst(): array
    {
        return $this->repository->getByQuery(Query::getInstance()->table('transactions')->orderBy(['id desc']));
    }
}
