<?php

// The compiled routes (ByJG\RestServer\Route\CompiledRouteList::compile()). Do not edit.

return array (
  'routes' => 
  array (
    'POST /webhook' => 
    array (
      0 => 'POST',
      1 => '/webhook',
      2 => 'ByJG\\RestServer\\OutputProcessor\\JsonOutputProcessor',
      3 => false,
      4 => 
      array (
        0 => 'Shop\\Controller\\WebhookController',
        1 => 'receive',
      ),
      5 => 
      array (
        'openapi_base_path' => '',
        'openapi_path' => '/webhook',
      ),
    ),
  ),
  'dispatch' => 
  array (
    0 => 
    array (
      'POST' => 
      array (
        '/webhook' => 'POST /webhook',
      ),
    ),
    1 => 
    array (
    ),
  ),
);
