<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SAP Business One Service Layer Connection
    |--------------------------------------------------------------------------
    |
    | Configure the connection details for the SAP Business One Service Layer.
    | These settings are used to authenticate and establish a session.
    |
    | - `server`: The full URL to the SAP Business One Service Layer.
    |             Example: "https://sap-server:50000/b1s/v1"
    |
    | - `database`: The name of the company database to connect to.
    |
    | - `username`: The username for authentication.
    |
    | - `password`: The password for authentication.
    |
    | - `cache_ttl`: The session cache Time To Live in seconds.
    |                Defaults to 1800 seconds (30 minutes).
    */
    'server'   => env('SAPB1_SERVER'),
    'database' => env('SAPB1_DATABASE'),
    'username' => env('SAPB1_USERNAME'),
    'password' => env('SAPB1_PASSWORD'),
    'cache_ttl' => env('SAPB1_CACHE_TTL', 1800),
    'verify_ssl' => env('SAPB1_VERIFY_SSL', true),
];
