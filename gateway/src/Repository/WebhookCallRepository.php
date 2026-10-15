<?php

namespace Gateway\Repository;

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\Gluo\Repository\BaseRepository;
use ByJG\MicroOrm\Exception\InvalidArgumentException;
use ByJG\MicroOrm\Exception\OrmModelInvalidException;
use ByJG\MicroOrm\Query;
use ByJG\MicroOrm\Repository;
use Gateway\Model\WebhookCall;
use ReflectionException;

class WebhookCallRepository extends BaseRepository
{
    /**
     * @throws OrmModelInvalidException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function __construct(DatabaseExecutor $executor)
    {
        $this->repository = new Repository($executor, WebhookCall::class);
    }

    /**
     * @return WebhookCall[] Newest first
     */
    public function forTransaction(int $transactionId): array
    {
        $query = Query::getInstance()
            ->table('webhook_call')
            ->where('webhook_call.transaction_id = :id', ['id' => $transactionId])
            ->orderBy(['id desc']);

        return $this->repository->getByQuery($query);
    }
}
