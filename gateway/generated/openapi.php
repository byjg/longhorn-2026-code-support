<?php

// public/docs/openapi.json as a PHP array (php gluo openapi). Do not edit.

return array (
  'openapi' => '3.1.0',
  'info' => 
  array (
    'title' => 'API Title',
    'description' => 'Information about the API',
    'termsOfService' => 'http://localhost:8080/terms/',
    'contact' => 
    array (
      'email' => 'someone@example.com',
    ),
    'license' => 
    array (
      'name' => 'Proprietary',
    ),
    'version' => '1.0.0',
  ),
  'paths' => 
  array (
    '/transactions' => 
    array (
      'post' => 
      array (
        'tags' => 
        array (
          0 => 'Transactions',
        ),
        'summary' => 'The shop registers a payment and says where to send the webhook.',
        'operationId' => 'POST::/transactions::Gateway\\Controller\\TransactionController::register',
        'requestBody' => 
        array (
          'required' => true,
          'content' => 
          array (
            'application/json' => 
            array (
              'schema' => 
              array (
                'required' => 
                array (
                  0 => 'payment_id',
                  1 => 'amount',
                  2 => 'webhook_url',
                ),
                'properties' => 
                array (
                  'payment_id' => 
                  array (
                    'type' => 'integer',
                    'format' => 'int32',
                  ),
                  'amount' => 
                  array (
                    'type' => 'number',
                    'format' => 'float',
                  ),
                  'webhook_url' => 
                  array (
                    'type' => 'string',
                  ),
                ),
                'type' => 'object',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The gateway\'s reference for the payment',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'id',
                  ),
                  'properties' => 
                  array (
                    'id' => 
                    array (
                      'type' => 'integer',
                      'format' => 'int32',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
        ),
      ),
    ),
    '/operator' => 
    array (
      'get' => 
      array (
        'tags' => 
        array (
          0 => 'Page',
        ),
        'summary' => 'The operator\'s page: every transaction, its buttons, and what the shop answered.',
        'operationId' => 'GET::/operator::Gateway\\Controller\\TransactionController::page',
        'responses' => 
        array (
          200 => 
          array (
            'description' => 'The operator page',
            'content' => 
            array (
              'text/html' => 
              array (
              ),
            ),
          ),
        ),
      ),
    ),
    '/transactions/{id}/{action}' => 
    array (
      'post' => 
      array (
        'tags' => 
        array (
          0 => 'Page',
        ),
        'summary' => 'A button on the page: report an event to the shop, then go back to the page.',
        'operationId' => 'POST::/transactions/{id}/{action}::Gateway\\Controller\\TransactionController::act',
        'parameters' => 
        array (
          0 => 
          array (
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'schema' => 
            array (
              'type' => 'integer',
            ),
          ),
          1 => 
          array (
            'name' => 'action',
            'in' => 'path',
            'required' => true,
            'schema' => 
            array (
              'type' => 'string',
              'enum' => 
              array (
                0 => 'review',
                1 => 'approve',
                2 => 'decline',
                3 => 'expire',
                4 => 'resend',
              ),
            ),
          ),
        ),
        'responses' => 
        array (
          303 => 
          array (
            'description' => 'Back to the operator page',
          ),
          404 => 
          array (
            'description' => 'Unknown transaction',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  '$ref' => '#/components/schemas/error',
                ),
              ),
            ),
          ),
        ),
      ),
    ),
  ),
  'components' => 
  array (
    'schemas' => 
    array (
      'Transaction' => 
      array (
        'description' => 'A payment the shop registered. `status` is the last event the gateway reported.',
        'properties' => 
        array (
          'createdAt' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'date-time',
            'readOnly' => true,
          ),
          'updatedAt' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'date-time',
            'readOnly' => true,
          ),
          'id' => 
          array (
            'type' => 'integer',
            'format' => 'int32',
          ),
          'paymentId' => 
          array (
            'type' => 'integer',
            'format' => 'int32',
          ),
          'amount' => 
          array (
            'type' => 'number',
            'format' => 'float',
          ),
          'status' => 
          array (
            'type' => 'string',
          ),
          'webhookUrl' => 
          array (
            'type' => 'string',
          ),
        ),
        'type' => 'object',
        'xml' => 
        array (
          'name' => 'Transaction',
        ),
      ),
      'WebhookCall' => 
      array (
        'description' => 'One webhook the gateway sent, and what the shop answered.',
        'properties' => 
        array (
          'createdAt' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'date-time',
            'readOnly' => true,
          ),
          'updatedAt' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'date-time',
            'readOnly' => true,
          ),
          'id' => 
          array (
            'type' => 'integer',
            'format' => 'int32',
          ),
          'transactionId' => 
          array (
            'type' => 'integer',
            'format' => 'int32',
          ),
          'event' => 
          array (
            'type' => 'string',
          ),
          'requestBody' => 
          array (
            'type' => 'string',
          ),
          'responseStatus' => 
          array (
            'type' => 'integer',
            'format' => 'int32',
          ),
          'responseBody' => 
          array (
            'type' => 'string',
          ),
        ),
        'type' => 'object',
        'xml' => 
        array (
          'name' => 'WebhookCall',
        ),
      ),
      'error' => 
      array (
        'properties' => 
        array (
          'error' => 
          array (
            'properties' => 
            array (
              'type' => 
              array (
                'description' => 'A class de Exceção',
                'type' => 'string',
              ),
              'message' => 
              array (
                'description' => 'A mensagem de erro',
                'type' => 'string',
              ),
              'file' => 
              array (
                'description' => 'O arquivo que gerou o erro',
                'type' => 'string',
              ),
              'line' => 
              array (
                'description' => 'A linha do erro',
                'type' => 'integer',
              ),
            ),
            'type' => 'object',
          ),
        ),
        'type' => 'object',
        'externalDocs' => 
        array (
          'description' => 'Find out more about Swagger',
          'url' => 'http://localhost:8080/docs',
        ),
      ),
    ),
    'securitySchemes' => 
    array (
      'jwt-token' => 
      array (
        'type' => 'apiKey',
        'name' => 'Authorization',
        'in' => 'header',
      ),
    ),
  ),
  'tags' => 
  array (
    0 => 
    array (
      'name' => 'Transactions',
      'description' => 'Transactions',
    ),
    1 => 
    array (
      'name' => 'Page',
      'description' => 'Page',
    ),
  ),
);
