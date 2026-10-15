<?php

namespace Tests\Controller;

use ByJG\DbMigration\Database\SqliteDatabase;
use ByJG\Gluo\Testing\BaseApiTestCase as GluoBaseApiTestCase;

class BaseApiTestCase extends GluoBaseApiTestCase
{
    protected function getOpenApiPath(): string
    {
        return __DIR__ . '/../../public/docs/openapi.json';
    }

    #[\Override]
    protected function getDatabaseClass(): string
    {
        return SqliteDatabase::class;
    }
}
