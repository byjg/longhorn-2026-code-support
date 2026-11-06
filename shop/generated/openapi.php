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
    '/webhook' => 
    array (
      'post' => 
      array (
        'tags' => 
        array (
          0 => 'Webhook',
        ),
        'operationId' => 'POST::/webhook::Shop\\Controller\\WebhookController::receive',
        'requestBody' => 
        array (
          'description' => 'What the gateway saw happen to a payment',
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
                  1 => 'gateway',
                ),
                'properties' => 
                array (
                  'payment_id' => 
                  array (
                    'type' => 'integer',
                    'format' => 'int32',
                  ),
                  'gateway' => 
                  array (
                    'type' => 'string',
                    'enum' => 
                    array (
                      0 => 'requires_review',
                      1 => 'succeeded',
                      2 => 'declined',
                      3 => 'expired',
                    ),
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
            'description' => 'Where the payment went, or why it stayed',
            'content' => 
            array (
              'application/json' => 
              array (
                'schema' => 
                array (
                  'required' => 
                  array (
                    0 => 'payment_id',
                    1 => 'from',
                  ),
                  'properties' => 
                  array (
                    'payment_id' => 
                    array (
                      'type' => 'integer',
                      'format' => 'int32',
                    ),
                    'from' => 
                    array (
                      'type' => 'string',
                    ),
                    'to' => 
                    array (
                      'type' => 'string',
                    ),
                    'result' => 
                    array (
                      'type' => 'string',
                    ),
                    'reason' => 
                    array (
                      'type' => 'string',
                    ),
                  ),
                  'type' => 'object',
                ),
              ),
            ),
          ),
          404 => 
          array (
            'description' => 'Unknown payment',
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
      'Payment' => 
      array (
        'description' => 'The merchant\'s view of a payment. `status` is a Shop\\Payment\\PaymentState, stored as its
value; it is only ever written by Shop\\Payment\\PaymentMover, through recordMove().',
        'required' => 
        array (
          0 => 'id',
          1 => 'amount',
          2 => 'status',
        ),
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
          'amount' => 
          array (
            'type' => 'number',
            'format' => 'float',
          ),
          'status' => 
          array (
            'description' => 'Stored as its value (\'PAID\'); a value from the database that PaymentState does not have
is a ValueError.',
            'type' => 'string',
          ),
          'transitionName' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
          ),
          'gatewayRef' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
          ),
          'paidAt' => 
          array (
            'type' => 
            array (
              0 => 'string',
              1 => 'null',
            ),
            'format' => 'date-time',
          ),
        ),
        'type' => 'object',
        'xml' => 
        array (
          'name' => 'Payment',
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
      'name' => 'Webhook',
      'description' => 'Webhook',
    ),
  ),
);
