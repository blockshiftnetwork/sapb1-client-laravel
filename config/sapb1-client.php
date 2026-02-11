<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default SAP B1 Connection
    |--------------------------------------------------------------------------
    |
    | The name of the default connection to use when no connection is
    | explicitly specified. This should match one of the keys in the
    | "connections" array below.
    |
    */
    'default' => env('SAPB1_DEFAULT_CONNECTION', 'service_layer'),

    /*
    |--------------------------------------------------------------------------
    | SAP Business One Connections
    |--------------------------------------------------------------------------
    |
    | Configure multiple SAP Business One connections. Each connection has
    | its own server, credentials, and session management. Different hosts
    | maintain isolated sessions (cookies are not shared).
    |
    | Connection settings:
    | - `driver`:     The driver type: "servicelayer" or "gateway" (default: "servicelayer").
    |                 This determines the login/logout endpoint paths since the
    |                 SAP Gateway uses different routes than the Service Layer:
    |                   Service Layer: POST {server}/Login
    |                   Gateway:       POST {host}/login (absolute, at host root)
    | - `server`:     The full URL to the SAP endpoint.
    | - `database`:   The name of the company database to connect to.
    | - `username`:   The username for authentication.
    | - `password`:   The password for authentication.
    | - `cache_ttl`:  The session cache TTL in seconds (default: 1800).
    | - `pool_size`:  The number of sessions to keep in the pool (default: 1).
    | - `verify_ssl`: Whether to verify the SSL certificate (default: true).
    |
    */
    'connections' => [

        'service_layer' => [
            'driver' => 'servicelayer',
            'server' => env('SAPB1_SERVICE_LAYER_SERVER', 'https://example-sap-host.com/b1s/v1'),
            'database' => env('SAPB1_SERVICE_LAYER_DATABASE'),
            'username' => env('SAPB1_SERVICE_LAYER_USERNAME'),
            'password' => env('SAPB1_SERVICE_LAYER_PASSWORD'),
            'cache_ttl' => env('SAPB1_SERVICE_LAYER_CACHE_TTL', 1800),
            'pool_size' => env('SAPB1_SERVICE_LAYER_POOL_SIZE', 1),
            'verify_ssl' => env('SAPB1_SERVICE_LAYER_VERIFY_SSL', true),
        ],

        'gateway' => [
            'driver' => 'gateway',
            'server' => env('SAPB1_GATEWAY_SERVER', 'https://example-sap-host.com/rs/v1'),
            'database' => env('SAPB1_GATEWAY_DATABASE'),
            'username' => env('SAPB1_GATEWAY_USERNAME'),
            'password' => env('SAPB1_GATEWAY_PASSWORD'),
            'cache_ttl' => env('SAPB1_GATEWAY_CACHE_TTL', 1800),
            'pool_size' => env('SAPB1_GATEWAY_POOL_SIZE', 1),
            'verify_ssl' => env('SAPB1_GATEWAY_VERIFY_SSL', true),
        ],

    ],
];
