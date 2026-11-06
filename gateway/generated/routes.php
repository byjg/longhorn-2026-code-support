<?php

// The compiled routes (ByJG\RestServer\Route\CompiledRouteList::compile()). Do not edit.

return array (
  'routes' => 
  array (
    'GET /operator' => 
    array (
      0 => 'GET',
      1 => '/operator',
      2 => 'ByJG\\RestServer\\OutputProcessor\\HtmlOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'Gateway\\Controller\\TransactionController',
        1 => 'page',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/operator',
      ),
    ),
    'POST /transactions' => 
    array (
      0 => 'POST',
      1 => '/transactions',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'Gateway\\Controller\\TransactionController',
        1 => 'register',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/transactions',
      ),
    ),
    'POST /transactions/{id}/{action}' => 
    array (
      0 => 'POST',
      1 => '/transactions/{id}/{action}',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonCleanOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'Gateway\\Controller\\TransactionController',
        1 => 'act',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/transactions/{id}/{action}',
      ),
    ),
  ),
  'dispatch' => 
  array (
    0 => 
    array (
      'GET' => 
      array (
        '/operator' => 'GET /operator',
      ),
      'POST' => 
      array (
        '/transactions' => 'POST /transactions',
      ),
    ),
    1 => 
    array (
      'POST' => 
      array (
        0 => 
        array (
          'regex' => '~^(?|/transactions/([^/]+)/([^/]+))$~',
          'routeMap' => 
          array (
            3 => 
            array (
              0 => 'POST /transactions/{id}/{action}',
              1 => 
              array (
                'id' => 'id',
                'action' => 'action',
              ),
            ),
          ),
        ),
      ),
    ),
  ),
);
