<?php

use BlockshiftNetwork\SapB1Client\Facades\SapB1;
use BlockshiftNetwork\SapB1Client\SapB1Client;
use BlockshiftNetwork\SapB1Client\SapB1Manager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Shared Http::fake setup for manager tests.
 */
function managerHttpFakes(): void
{
    $loginResponse = Http::response(
        ['SessionId' => 'mock_session_id', 'Version' => '10.0'],
        200,
        ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']
    );

    Http::fake([
        '*Login*' => $loginResponse,
        '*/login' => $loginResponse,   // Gateway login endpoint (lowercase, host-root)
        '*Items*' => Http::response(['value' => [['ItemCode' => 'A001'], ['ItemCode' => 'A002']]], 200),
        '*BusinessPartners*' => Http::response(['value' => [['CardCode' => 'C001']]], 200),
        '*PDFExport*' => Http::response(['pdf' => 'base64data'], 200),
        '*Logout*' => Http::response(null, 204),
        '*/logout' => Http::response(null, 204), // Gateway logout endpoint
        '*' => Http::response('Not Found', 404),
    ]);
}

beforeEach(function () {
    Cache::flush();

    config()->set('sapb1-client', [
        'default' => 'service_layer',
        'connections' => [
            'service_layer' => [
                'driver' => 'servicelayer',
                'server' => 'https://sap-services/b1s/v1/',
                'database' => 'SBO_PROD',
                'username' => 'manager',
                'password' => 'password',
                'cache_ttl' => 1800,
                'pool_size' => 1,
                'verify_ssl' => false,
            ],
            'gateway' => [
                'driver' => 'gateway',
                'server' => 'https://sap-gateway/rs/v1/',
                'database' => 'SBO_PROD',
                'username' => 'manager',
                'password' => 'password',
                'cache_ttl' => 1800,
                'pool_size' => 1,
                'verify_ssl' => false,
            ],
        ],
    ]);
});

it('resolves named connections and returns SapB1Client instances', function () {
    managerHttpFakes();
    $manager = app(SapB1Manager::class);

    $slClient = $manager->connection('service_layer');
    $gwClient = $manager->connection('gateway');

    expect($slClient)->toBeInstanceOf(SapB1Client::class);
    expect($gwClient)->toBeInstanceOf(SapB1Client::class);
    expect($slClient)->not->toBe($gwClient);
});

it('caches client instances for the same connection name', function () {
    managerHttpFakes();
    $manager = app(SapB1Manager::class);

    $first = $manager->connection('service_layer');
    $second = $manager->connection('service_layer');

    expect($first)->toBe($second);
});

it('throws InvalidArgumentException for undefined connections', function () {
    managerHttpFakes();
    $manager = app(SapB1Manager::class);

    expect(fn () => $manager->connection('nonexistent'))
        ->toThrow(InvalidArgumentException::class, 'SAP B1 connection [nonexistent] not configured.');
});

it('proxies method calls to the default connection', function () {
    managerHttpFakes();
    $manager = app(SapB1Manager::class);

    $response = $manager->get('Items');

    expect($response->successful())->toBeTrue();
    expect($response->json('value'))->toHaveCount(2);
});

it('facade proxies to default connection', function () {
    managerHttpFakes();
    $response = SapB1::get('Items');

    expect($response->successful())->toBeTrue();
    expect($response->json('value'))->toHaveCount(2);
});

it('facade can use named connections', function () {
    managerHttpFakes();
    $response = SapB1::connection('gateway')->post('PDFExport', ['DocEntry' => 1]);

    expect($response->successful())->toBeTrue();
    expect($response->json('pdf'))->toBe('base64data');
});

it('supports gateway macro shorthand', function () {
    managerHttpFakes();
    $client = SapB1::gateway();

    expect($client)->toBeInstanceOf(SapB1Client::class);

    $response = $client->post('PDFExport', ['DocEntry' => 1]);
    expect($response->successful())->toBeTrue();
});

it('supports serviceLayer macro shorthand', function () {
    managerHttpFakes();
    $client = SapB1::serviceLayer();

    expect($client)->toBeInstanceOf(SapB1Client::class);

    $response = $client->get('Items');
    expect($response->successful())->toBeTrue();
});

it('uses different session cache keys for different connections', function () {
    managerHttpFakes();
    $manager = app(SapB1Manager::class);

    $manager->connection('service_layer');
    $manager->connection('gateway');

    $slKey = 'sapb1-session:'.md5('https://sap-services/b1s/v1/SBO_PRODmanager').':0';
    $gwKey = 'sapb1-session:'.md5('https://sap-gateway/rs/v1/SBO_PRODmanager').':0';

    expect(Cache::has($slKey))->toBeTrue();
    expect(Cache::has($gwKey))->toBeTrue();
    expect($slKey)->not->toBe($gwKey);
});

it('resetAllForNewRequest resets all cached clients', function () {
    managerHttpFakes();
    $manager = app(SapB1Manager::class);

    // Create connections to populate the cache
    $slClient = $manager->connection('service_layer');
    $gwClient = $manager->connection('gateway');

    // Add headers to simulate request state
    $slClient->withHeaders(['X-Test' => 'value']);
    $gwClient->withHeaders(['X-Test' => 'value']);

    // Reset all clients
    $manager->resetAllForNewRequest();

    // Make requests - headers should not be present (they were reset)
    $slClient->get('Items');
    $gwClient->post('PDFExport', []);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Items') || str_contains($request->url(), 'PDFExport')) {
            return ! $request->hasHeader('X-Test');
        }

        return true;
    });
});

it('purge removes a specific cached client', function () {
    managerHttpFakes();
    $manager = app(SapB1Manager::class);

    $first = $manager->connection('service_layer');
    expect($manager->getClients())->toHaveKey('service_layer');

    $manager->purge('service_layer');
    expect($manager->getClients())->not->toHaveKey('service_layer');

    // Reconnecting should create a new instance
    $second = $manager->connection('service_layer');
    expect($second)->not->toBe($first);
});

it('purge without arguments removes all cached clients', function () {
    managerHttpFakes();
    $manager = app(SapB1Manager::class);

    $manager->connection('service_layer');
    $manager->connection('gateway');
    expect($manager->getClients())->toHaveCount(2);

    $manager->purge();
    expect($manager->getClients())->toHaveCount(0);
});

it('manager is registered as singleton', function () {
    $first = app(SapB1Manager::class);
    $second = app(SapB1Manager::class);

    expect($first)->toBe($second);
});

it('manager is aliased as sapb1', function () {
    $fromClass = app(SapB1Manager::class);
    $fromAlias = app('sapb1');

    expect($fromClass)->toBe($fromAlias);
});

it('facade query method returns fluent builder on default connection', function () {
    managerHttpFakes();
    $response = SapB1::query('BusinessPartners')
        ->select('CardCode', 'CardName')
        ->where('CardCode', 'C001')
        ->get();

    expect($response->successful())->toBeTrue();
    expect($response->json('value'))->toHaveCount(1);
});

it('named connection supports fluent query builder', function () {
    managerHttpFakes();
    $response = SapB1::connection('service_layer')
        ->query('Items')
        ->select('ItemCode')
        ->top(2)
        ->get();

    expect($response->successful())->toBeTrue();
    expect($response->json('value'))->toHaveCount(2);
});

it('query find retrieves a single record by string key', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), "BusinessPartners('C001')")) {
            return Http::response(['CardCode' => 'C001', 'CardName' => 'Acme Corp'], 200);
        }

        return Http::response('Not Found', 404);
    });

    $response = SapB1::query('BusinessPartners')->find('C001');

    expect($response->successful())->toBeTrue();
    expect($response->json('CardCode'))->toBe('C001');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), "BusinessPartners('C001')");
    });
});

it('query find retrieves a single record by numeric key', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), 'Orders(123)')) {
            return Http::response(['DocEntry' => 123, 'DocNum' => 456], 200);
        }

        return Http::response('Not Found', 404);
    });

    $response = SapB1::query('Orders')->find(123);

    expect($response->successful())->toBeTrue();
    expect($response->json('DocEntry'))->toBe(123);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'Orders(123)');
    });
});

it('query find respects select fields', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), 'BusinessPartners')) {
            return Http::response(['CardCode' => 'C001'], 200);
        }

        return Http::response('Not Found', 404);
    });

    SapB1::query('BusinessPartners')->select('CardCode', 'CardName')->find('C001');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'BusinessPartners') && ! str_contains($request->url(), 'Login')) {
            return str_contains($request->url(), '%24select=CardCode%2CCardName')
                || str_contains($request->url(), '$select=CardCode,CardName');
        }

        return true;
    });
});

it('query create posts a new record', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), 'BusinessPartners') && $request->method() === 'POST') {
            return Http::response(['CardCode' => 'C002', 'CardName' => 'New Partner'], 201);
        }

        return Http::response('Not Found', 404);
    });

    $response = SapB1::query('BusinessPartners')->create([
        'CardCode' => 'C002',
        'CardName' => 'New Partner',
        'CardType' => 'cCustomer',
    ]);

    expect($response->successful())->toBeTrue();
    expect($response->json('CardCode'))->toBe('C002');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'BusinessPartners') && ! str_contains($request->url(), 'Login')) {
            return $request->method() === 'POST'
                && $request['CardCode'] === 'C002';
        }

        return true;
    });
});

it('query update patches an existing record', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), 'BusinessPartners') && $request->method() === 'PATCH') {
            return Http::response(null, 204);
        }

        return Http::response('Not Found', 404);
    });

    $response = SapB1::query('BusinessPartners')->update('C001', [
        'CardName' => 'Updated Name',
    ]);

    expect($response->status())->toBe(204);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'BusinessPartners') && ! str_contains($request->url(), 'Login')) {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), "BusinessPartners('C001')")
                && $request['CardName'] === 'Updated Name';
        }

        return true;
    });
});

it('query delete removes a record', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), 'BusinessPartners') && $request->method() === 'DELETE') {
            return Http::response(null, 204);
        }

        return Http::response('Not Found', 404);
    });

    $response = SapB1::query('BusinessPartners')->delete('C001');

    expect($response->status())->toBe(204);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'BusinessPartners') && ! str_contains($request->url(), 'Login')) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), "BusinessPartners('C001')");
        }

        return true;
    });
});

it('query update sends replace collections header when enabled', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), 'Orders') && $request->method() === 'PATCH') {
            return Http::response(null, 204);
        }

        return Http::response('Not Found', 404);
    });

    SapB1::query('Orders')->replaceCollections()->update(123, [
        'DocumentLines' => [
            ['ItemCode' => 'A001', 'Quantity' => 5],
        ],
    ]);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Orders(123)') && $request->method() === 'PATCH') {
            return $request->hasHeader('B1S-ReplaceCollectionsOnPatch', 'true');
        }

        return true;
    });
});

it('query update does not send replace collections header by default', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), 'Orders') && $request->method() === 'PATCH') {
            return Http::response(null, 204);
        }

        return Http::response('Not Found', 404);
    });

    SapB1::query('Orders')->update(123, ['Comments' => 'Simple update']);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Orders(123)') && $request->method() === 'PATCH') {
            return ! $request->hasHeader('B1S-ReplaceCollectionsOnPatch');
        }

        return true;
    });
});

it('query crud works on named connections', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login') || str_contains($request->url(), 'login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), 'Items')) {
            return Http::response(['ItemCode' => 'A001', 'ItemName' => 'Widget'], 200);
        }

        return Http::response('Not Found', 404);
    });

    $response = SapB1::gateway()->query('Items')->find('A001');

    expect($response->successful())->toBeTrue();
    expect($response->json('ItemCode'))->toBe('A001');
});

// ──────────────────────────────────────────────────
// Driver-specific tests
// ──────────────────────────────────────────────────

it('servicelayer driver posts login to relative Login endpoint', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }

        return Http::response('Not Found', 404);
    });

    new SapB1Client([
        'driver' => 'servicelayer',
        'server' => 'https://sap-services/b1s/v1/',
        'database' => 'SBO_PROD',
        'username' => 'manager',
        'password' => 'password',
        'verify_ssl' => false,
    ]);

    Http::assertSent(function ($request) {
        // Service Layer login should resolve to {server}/Login
        return $request->method() === 'POST'
            && str_contains($request->url(), 'b1s/v1/Login');
    });
});

it('gateway driver posts login to absolute host-root /login endpoint', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }

        return Http::response('Not Found', 404);
    });

    new SapB1Client([
        'driver' => 'gateway',
        'server' => 'https://sap-gateway/rs/v1/',
        'database' => 'SBO_PROD',
        'username' => 'manager',
        'password' => 'password',
        'verify_ssl' => false,
    ]);

    Http::assertSent(function ($request) {
        // Gateway login should be absolute: https://sap-gateway/login (NOT /rs/v1/Login)
        return $request->method() === 'POST'
            && $request->url() === 'https://sap-gateway/login';
    });
});

it('gateway driver posts logout to absolute host-root /logout endpoint', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), '/logout')) {
            return Http::response(null, 204);
        }

        return Http::response('Not Found', 404);
    });

    $client = new SapB1Client([
        'driver' => 'gateway',
        'server' => 'https://sap-gateway/rs/v1/',
        'database' => 'SBO_PROD',
        'username' => 'manager',
        'password' => 'password',
        'verify_ssl' => false,
    ]);

    $client->logout();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'logout')) {
            return $request->url() === 'https://sap-gateway/logout';
        }

        return true;
    });
});

it('gateway driver preserves port in login url', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }

        return Http::response('Not Found', 404);
    });

    new SapB1Client([
        'driver' => 'gateway',
        'server' => 'https://sap-gateway:50000/rs/v1/',
        'database' => 'SBO_PROD',
        'username' => 'manager',
        'password' => 'password',
        'verify_ssl' => false,
    ]);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://sap-gateway:50000/login';
    });
});

it('defaults to servicelayer driver when driver config is not set', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }

        return Http::response('Not Found', 404);
    });

    // No 'driver' key in config — should default to servicelayer behavior
    new SapB1Client([
        'server' => 'https://sap-services/b1s/v1/',
        'database' => 'SBO_PROD',
        'username' => 'manager',
        'password' => 'password',
        'verify_ssl' => false,
    ]);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'b1s/v1/Login');
    });
});

it('throws exception for invalid driver value', function () {
    Http::fake();

    expect(fn () => new SapB1Client([
        'driver' => 'invalid_driver',
        'server' => 'https://sap-services/b1s/v1/',
        'database' => 'SBO_PROD',
        'username' => 'manager',
        'password' => 'password',
        'verify_ssl' => false,
    ]))->toThrow(InvalidArgumentException::class, 'Invalid driver [invalid_driver]');
});

it('manager resolves gateway connection with correct driver login', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/login') || str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), 'Items')) {
            return Http::response(['value' => [['ItemCode' => 'A001']]], 200);
        }

        return Http::response('Not Found', 404);
    });

    $response = SapB1::gateway()->get('Items');

    expect($response->successful())->toBeTrue();

    // Verify the gateway login was called at the host root
    Http::assertSent(function ($request) {
        if ($request->method() === 'POST' && str_contains($request->url(), 'login')) {
            return $request->url() === 'https://sap-gateway/login';
        }

        return true;
    });
});
