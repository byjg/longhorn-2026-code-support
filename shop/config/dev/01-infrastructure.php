<?php

use ByJG\AnyDataset\Db\DatabaseExecutor;
use ByJG\AnyDataset\Db\Factory;
use ByJG\AnyDataset\Db\Interfaces\DbDriverInterface;
use ByJG\Cache\Psr16\BaseCacheEngine;
use ByJG\Cache\Psr16\NoCacheEngine;
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\LazyParam;
use ByJG\Config\Config;
use ByJG\Config\Param;
use ByJG\MicroOrm\ORM;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

return [

    // Cache Configuration
    BaseCacheEngine::class => DI::bind(NoCacheEngine::class)
        ->toSingleton(),

    // Database Configuration
    DbDriverInterface::class => DI::bind(Factory::class)
        ->withFactoryMethod("getDbRelationalInstance", [Param::get('DBDRIVER_CONNECTION')])
        ->toSingleton(),

    DatabaseExecutor::class => DI::bind(DatabaseExecutor::class)
        ->withInjectedConstructor()
        ->toSingleton(),

    // ORM Initialization - Required for ActiveRecord pattern
    // This sets the default database driver for all ActiveRecord models
    "ORMInitialization" => DI::bind(ORM::class)
        ->withFactoryMethod("defaultDbDriver", [LazyParam::get(DatabaseExecutor::class)])
        ->toEagerSingleton(),

    // Logging Configuration
    // The framework's logger (HTTP server errors) stays out of the payments log.
    LoggerInterface::class => DI::bind(NullLogger::class)
        ->toSingleton(),

    // The payments log: one line per move, refusal and action, so `tail -f` tells the story.
    'PaymentsLogger' => function () {
        $handler = new StreamHandler(Config::get('PAYMENTS_LOG'));
        $handler->setFormatter(new LineFormatter("%datetime% %message%\n", "H:i:s"));

        return new Logger('shop', [$handler]);
    },

];
