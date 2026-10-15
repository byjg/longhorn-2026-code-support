<?php

use ByJG\Config\Config;
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;
use ByJG\JwtWrapper\JwtHashHmacSecret;
use ByJG\JwtWrapper\JwtKeyInterface;
use ByJG\JwtWrapper\JwtWrapper;

// The demo has no users and no login: nothing here is protected. JWT stays bound only
// because the JwtMiddleware in 03-api.php is part of the request pipeline.
return [

    // JWT Configuration
    JwtKeyInterface::class => DI::bind(JwtHashHmacSecret::class)
        ->withConstructorArgs([Param::get('JWT_SECRET')])
        ->toSingleton(),

    JwtWrapper::class => DI::bind(JwtWrapper::class)
        ->withConstructorArgs([Param::get('API_SERVER'), Param::get(JwtKeyInterface::class)])
        ->toSingleton(),

    // CORS Configuration
    'CORS_SERVER_LIST' => function () {
        return preg_split('/,(?![^{}]*})/', Config::get('CORS_SERVERS'));
    },

];
