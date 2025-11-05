<?php

use BlockshiftNetwork\SapB1Client\Tests\TestCase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class)->in(__DIR__);

/**
 * Generate SAP B1 login response payload
 */
function sapLoginResponsePayload(
    string $baseUri = 'https://sap-server/b1s/v1/',
    string $sessionId = 'mock_session_id',
    string $version = '1000190',
    int $timeout = 30
): array {
    $base = rtrim($baseUri, '/');

    return [
        'odata.metadata' => $base . '/$metadata#B1Sessions/@Element',
        'SessionId' => $sessionId,
        'Version' => $version,
        'SessionTimeout' => $timeout,
    ];
}

/**
 * Generate SAP B1 login response headers
 */
function sapLoginResponseHeaders(string $sessionCookie = 'mock_session_cookie'): array
{
    return [
        'Set-Cookie' => "B1SESSION={$sessionCookie};HttpOnly;;Secure;SameSite=None",
    ];
}

/**
 * Generate a complete SAP B1 login HTTP response
 */
function sapLoginHttpResponse(
    string $baseUri = 'https://sap-server/b1s/v1/',
    string $sessionId = 'mock_session_id',
    string $sessionCookie = 'mock_session_cookie',
    string $version = '1000190',
    int $timeout = 30
) {
    return Http::response(
        sapLoginResponsePayload($baseUri, $sessionId, $version, $timeout),
        200,
        sapLoginResponseHeaders($sessionCookie)
    );
}
