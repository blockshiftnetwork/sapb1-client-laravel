<?php

use BlockshiftNetwork\SapB1Client\Facades\SapB1;
use BlockshiftNetwork\SapB1Client\ODataQuery;
use BlockshiftNetwork\SapB1Client\SapB1Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    Http::fake([
        '*Login*' => Http::sequence()
            ->push(['SessionId' => 'mock_session_id', 'Version' => '10.0'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;'])
            ->push('Login failed', 500)
            ->push(['SessionId' => 'new_mock_session_id', 'Version' => '10.0'], 200, ['Set-Cookie' => 'B1SESSION=new_mock_session_cookie;']),
        '*Items*' => Http::response(['value' => [['ItemCode' => 'A001'], ['ItemCode' => 'A002']]], 200),
        '*BusinessPartners*' => Http::sequence()
            ->push(['CardCode' => 'C2024', 'CardName' => 'Test Customer'], 201)
            ->push(['CardCode' => 'C2024', 'CardName' => 'Updated Customer'], 200)
            ->push(null, 204)
            ->push(null, 204),
        '*Logout*' => Http::response(null, 204),
        '*' => Http::response('Not Found', 404),
    ]);

    config()->set('sapb1-client.server', 'https://sap-server/b1s/v1/');
    config()->set('sapb1-client.database', 'SBO_PROD');
    config()->set('sapb1-client.username', 'manager');
    config()->set('sapb1-client.password', 'password');
    config()->set('sapb1-client.cache_ttl', 1800);
    config()->set('sapb1-client.verify_ssl', false);
    config()->set('sapb1-client.pool_size', 1);
});

it('can login and cache the session', function () {
    $sessionKey = 'sapb1-session:'.md5('https://sap-server/b1s/v1/SBO_PRODmanager') . ':0';

    expect(Cache::has($sessionKey))->toBeFalse();

    $client = new SapB1Client;

    expect(Cache::has($sessionKey))->toBeTrue();
    expect(Cache::get($sessionKey))->toContain('B1SESSION=');
});

it('stores and sends session cookies correctly', function () {
    // This test verifies that cookies from login are stored and sent in subsequent requests
    // The beforeEach already sets up a fake with B1SESSION cookie
    
    $client = new SapB1Client([], 0);
    $client->get('Items');

    // Verify session cache contains the session cookie
    $sessionKey = 'sapb1-session:'.md5('https://sap-server/b1s/v1/SBO_PRODmanager'). ':0';
    $cachedCookie = Cache::get($sessionKey);

    expect($cachedCookie)->toContain('B1SESSION=');

    // Verify Cookie header is sent in subsequent requests
    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Items')) {
            $cookieHeader = $request->header('Cookie')[0] ?? '';

            return str_contains($cookieHeader, 'B1SESSION=');
        }

        return true;
    });
});

it('validates required configuration', function () {
    Cache::flush();

    expect(fn () => new SapB1Client([
        'server' => '',
        'database' => '',
        'username' => '',
        'password' => '',
    ]))->toThrow(InvalidArgumentException::class, 'Missing required configuration');
});

it('can make get requests', function () {
    $client = new SapB1Client;
    $response = $client->get('Items');

    expect($response->successful())->toBeTrue();
    expect($response->json('value'))->toBe([['ItemCode' => 'A001'], ['ItemCode' => 'A002']]);
});

it('can use facade to make get requests', function () {
    $response = SapB1::get('Items');

    expect($response->successful())->toBeTrue();
    expect($response->json('value'))->toHaveCount(2);
});

it('can make post requests', function () {
    $client = new SapB1Client;
    $response = $client->post('BusinessPartners', ['CardCode' => 'C2024']);

    expect($response->successful())->toBeTrue();
    expect($response->json('CardCode'))->toBe('C2024');
});

it('can make put requests', function () {
    $client = new SapB1Client;

    Http::fake([
        '*Login*' => Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']),
        '*BusinessPartners*' => Http::response(['CardCode' => 'C2024', 'CardName' => 'Updated via PUT'], 200),
    ]);

    $response = $client->put("BusinessPartners('C2024')", ['CardName' => 'Updated Name']);

    expect($response->successful())->toBeTrue();
});

it('can make patch requests', function () {
    $client = new SapB1Client;
    $response = $client->patch("BusinessPartners('C2024')", ['CardName' => 'New Name']);

    expect($response->successful())->toBeTrue();
});

it('can make delete requests', function () {
    $client = new SapB1Client;
    $response = $client->delete("BusinessPartners('C2024')");

    expect($response->successful())->toBeTrue();
});

it('can make odata queries with array', function () {
    $client = new SapB1Client;
    $response = $client->odataQuery('Items', ['$top' => 2]);

    expect($response->successful())->toBeTrue();
    expect($response->json('value'))->toBe([['ItemCode' => 'A001'], ['ItemCode' => 'A002']]);
});

it('can make odata queries with query builder', function () {
    $query = (new ODataQuery)
        ->select('ItemCode', 'ItemName')
        ->where('ItemCode', 'A001')
        ->top(5);

    $client = new SapB1Client;
    $response = $client->odataQuery('Items', $query);

    expect($response->successful())->toBeTrue();
    expect($response->json())->toHaveKey('value');
});

it('can add custom headers to a request', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), 'Items')) {
            return Http::response(['value' => []], 200);
        }

        return Http::response('Not Found', 404);
    });

    Cache::flush();
    $client = new SapB1Client;
    $client->withHeaders(['X-Custom-Header' => 'CustomValue'])->get('Items');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'Items') && $request->hasHeader('X-Custom-Header', 'CustomValue');
    });
});

it('custom headers are only applied to the next request', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }
        if (str_contains($request->url(), 'Items')) {
            return Http::response(['value' => []], 200);
        }

        return Http::response('Not Found', 404);
    });

    Cache::flush();
    $client = new SapB1Client;
    $client->withHeaders(['X-Custom-Header' => 'CustomValue'])->get('Items');
    $client->get('Items');

    // Verificar que solo el primer request tiene el header
    $itemsRequests = collect(Http::recorded())
        ->filter(fn ($record) => str_contains($record[0]->url(), 'Items'))
        ->values();

    expect($itemsRequests)->toHaveCount(2);
    expect($itemsRequests[0][0]->hasHeader('X-Custom-Header', 'CustomValue'))->toBeTrue();
    expect($itemsRequests[1][0]->hasHeader('X-Custom-Header'))->toBeFalse();
});

it('can logout and clear the session', function () {
    $sessionKey = 'sapb1-session:'.md5('https://sap-server/b1s/v1/SBO_PRODmanager') . ':0';

    $client = new SapB1Client;
    expect(Cache::has($sessionKey))->toBeTrue();

    $client->logout();

    expect(Cache::has($sessionKey))->toBeFalse();
});

it('can use the facade', function () {
    $response = SapB1::get('Items');

    expect($response->successful())->toBeTrue();
    expect($response->json('value'))->toHaveCount(2);
});

it('facade can make odata queries', function () {
    $query = (new ODataQuery)
        ->select('CardCode', 'CardName')
        ->where('CardType', 'cCustomer')
        ->top(10);

    $response = SapB1::odataQuery('BusinessPartners', $query);

    expect($response->successful())->toBeTrue();
});

it('facade can use custom headers', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']);
        }

        return Http::response(['value' => []], 200);
    });

    Cache::flush();
    SapB1::withHeaders(['X-Custom-Header' => 'TestValue'])->get('Items');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'Items') && $request->hasHeader('X-Custom-Header', 'TestValue');
    });
});

it('can use sendRequestWithCallback', function () {
    $client = new SapB1Client;

    $response = $client->sendRequestWithCallback(function ($httpClient) {
        return $httpClient->get('Items');
    });

    expect($response->successful())->toBeTrue();
    expect($response->json('value'))->toHaveCount(2);
});

it('facade can use sendRequestWithCallback', function () {
    $response = SapB1::sendRequestWithCallback(function ($httpClient) {
        return $httpClient->get('Items', ['$top' => 1]);
    });

    expect($response->successful())->toBeTrue();
    expect($response->json())->toHaveKey('value');
});

it('can use http macro SapB1', function () {
    $response = Http::SapB1([])->get('Items');

    expect($response->successful())->toBeTrue();
    expect($response->json('value'))->toHaveCount(2);
});

it('http macro can accept custom config', function () {
    $customClient = Http::SapB1([
        'server' => 'https://custom-server/b1s/v1/',
        'database' => 'CUSTOM_DB',
        'username' => 'custom_user',
        'password' => 'custom_pass',
    ]);

    expect($customClient)->toBeInstanceOf(SapB1Client::class);
});

it('can make concurrent requests with pool', function () {
    $client = new SapB1Client;

    $responses = $client->pool(function ($pool) {
        return [
            $pool->as('items')->get('Items', ['$top' => 2]),
            $pool->as('partners')->get('BusinessPartners', ['$top' => 1]),
        ];
    });

    expect($responses)->toHaveKey('items');
    expect($responses)->toHaveKey('partners');
    expect($responses['items']->successful())->toBeTrue();
    expect($responses['partners']->successful())->toBeTrue();
    expect($responses['items']->json('value'))->toHaveCount(2);
});

it('facade can make concurrent requests with pool', function () {
    $responses = SapB1::pool(function ($pool) {
        return [
            $pool->as('items')->get('Items', ['$top' => 2]),
            $pool->as('partners')->get('BusinessPartners'),
        ];
    });

    expect($responses)->toHaveKey('items');
    expect($responses)->toHaveKey('partners');
    expect($responses['items']->successful())->toBeTrue();
    expect($responses['partners']->successful())->toBeTrue();
});

it('pool supports all http methods', function () {
    Http::fake([
        '*Login*' => Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']),
        '*Items*' => Http::response(['value' => []], 200),
        '*BusinessPartners*' => Http::response(['CardCode' => 'C001'], 200),
    ]);

    $client = new SapB1Client;

    $responses = $client->pool(function ($pool) {
        return [
            $pool->as('get')->get('Items'),
            $pool->as('post')->post('BusinessPartners', ['CardCode' => 'C001']),
        ];
    });

    expect($responses)->toHaveKey('get');
    expect($responses)->toHaveKey('post');
    expect($responses['get']->successful())->toBeTrue();
    expect($responses['post']->successful())->toBeTrue();
});

it('uses distinct sessions for different indices', function () {
    $baseKey = 'sapb1-session:'.md5('https://sap-server/b1s/v1/SBO_PRODmanager');
    
    // Client 1 (Index 0)
    $client1 = new SapB1Client([], 0);
    expect(Cache::has("{$baseKey}:0"))->toBeTrue();
    
    // Client 2 (Index 1)
    $client2 = new SapB1Client([], 1);
    expect(Cache::has("{$baseKey}:1"))->toBeTrue();
    
    // Ensure keys are distinct
    expect("{$baseKey}:0")->not->toBe("{$baseKey}:1");
});

it('automatically selects random index when pool size > 1', function () {
    config()->set('sapb1-client.pool_size', 5);
    
    // Mocking rand() isn't easy in global scope without namespacing tricks, 
    // but we can check if the session key generated ends in a valid index
    
    $client = new SapB1Client;
    
    // We access the protected sessionIndex via reflection to verify
    $reflection = new ReflectionClass($client);
    $property = $reflection->getProperty('sessionIndex');
    $property->setAccessible(true);
    $index = $property->getValue($client);
    
    expect($index)->toBeGreaterThanOrEqual(0);
    expect($index)->toBeLessThan(5);
});
