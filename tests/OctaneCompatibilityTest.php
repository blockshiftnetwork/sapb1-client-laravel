<?php

use BlockshiftNetwork\SapB1Client\Facades\SapB1;
use BlockshiftNetwork\SapB1Client\SapB1Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('sapb1-client.server', 'https://sap-server/b1s/v1/');
    config()->set('sapb1-client.database', 'SBO_PROD');
    config()->set('sapb1-client.username', 'manager');
    config()->set('sapb1-client.password', 'password');
    config()->set('sapb1-client.cache_ttl', 1800);
    config()->set('sapb1-client.verify_ssl', false);
});

it('automatically renews session on 401 unauthorized response', function () {
    Cache::flush();

    Http::fake([
        '*Login*' => Http::sequence()
            ->push(['SessionId' => 'initial_session'], 200, ['Set-Cookie' => 'B1SESSION=initial_cookie;'])
            ->push(['SessionId' => 'renewed_session'], 200, ['Set-Cookie' => 'B1SESSION=renewed_cookie;']),
        '*Orders*' => Http::sequence()
            ->push('Unauthorized', 401) // First call fails with expired session
            ->push(['value' => [['DocNum' => '1001']]], 200), // Second call succeeds after renewal
        '*' => Http::response('Not Found', 404),
    ]);

    $client = new SapB1Client;

    // This should trigger automatic session renewal
    $response = $client->get('Orders');

    expect($response->successful())->toBeTrue();
    expect($response->json('value'))->toHaveCount(1);
    expect($response->json('value.0.DocNum'))->toBe('1001');

    // Verify that login was called twice (initial + renewal)
    Http::assertSentCount(4); // 1 initial login, 1 failed Orders request, 1 re-login, 1 successful Orders request
});

it('automatically renews session on 403 forbidden response', function () {
    Cache::flush();

    Http::fake([
        '*Login*' => Http::sequence()
            ->push(['SessionId' => 'initial_session'], 200, ['Set-Cookie' => 'B1SESSION=initial_cookie;'])
            ->push(['SessionId' => 'renewed_session'], 200, ['Set-Cookie' => 'B1SESSION=renewed_cookie;']),
        '*Invoices*' => Http::sequence()
            ->push('Forbidden', 403) // First call fails with expired session
            ->push(['value' => [['DocEntry' => '5001']]], 200), // Second call succeeds after renewal
        '*' => Http::response('Not Found', 404),
    ]);

    $client = new SapB1Client;

    // This should trigger automatic session renewal
    $response = $client->get('Invoices');

    expect($response->successful())->toBeTrue();
    expect($response->json('value.0.DocEntry'))->toBe('5001');
});

it('clears cached session when forcing new login', function () {
    // Assuming index 0 by default
    $sessionKey = 'sapb1-session:'.md5('https://sap-server/b1s/v1/SBO_PRODmanager') . ':0';

    Cache::flush();


    Http::fake([
        '*Login*' => Http::sequence()
            ->push(['SessionId' => 'first_session'], 200, ['Set-Cookie' => 'B1SESSION=first_cookie;'])
            ->push(['SessionId' => 'second_session'], 200, ['Set-Cookie' => 'B1SESSION=second_cookie;']),
        '*Items*' => Http::sequence()
            ->push('Unauthorized', 401)
            ->push(['value' => []], 200),
        '*' => Http::response('Not Found', 404),
    ]);

    $client = new SapB1Client;

    expect(Cache::get($sessionKey))->toContain('first_cookie');

    // Trigger session renewal
    $client->get('Items');


    // Session should be updated in cache
    expect(Cache::get($sessionKey))->toContain('second_cookie');
});

it('does not retry infinitely on persistent 401 errors', function () {
    Cache::flush();

    Http::fake([
        '*Login*' => Http::sequence()
            ->push(['SessionId' => 'session1'], 200, ['Set-Cookie' => 'B1SESSION=cookie1;'])
            ->push(['SessionId' => 'session2'], 200, ['Set-Cookie' => 'B1SESSION=cookie2;']),
        '*Quotations*' => Http::response('Unauthorized', 401), // Always fails
        '*' => Http::response('Not Found', 404),
    ]);

    $client = new SapB1Client;

    // This should only retry once
    $response = $client->get('Quotations');

    expect($response->status())->toBe(401);
    // Should have: 1 initial login + 1 failed request + 1 re-login + 1 failed retry = 4 requests
    Http::assertSentCount(4);
});

it('works correctly with scoped binding for octane compatibility', function () {
    Cache::flush();

    Http::fake([
        '*Login*' => Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']),
        '*Items*' => Http::response(['value' => [['ItemCode' => 'A001'], ['ItemCode' => 'A002']]], 200),
        '*' => Http::response('Not Found', 404),
    ]);

    // First "request" - create new instance
    $client1 = app(SapB1Client::class);
    $response1 = $client1->get('Items');

    // Second "request" - should get same scoped instance within same request
    $client2 = app(SapB1Client::class);
    expect($client1)->toBe($client2);

    // Simulate new request context by flushing the scoped instances
    app()->forgetScopedInstances();

    // Third "request" - should get a fresh instance (simulating new Octane request)
    $client3 = app(SapB1Client::class);
    expect($client3)->not->toBe($client1);
});

it('facade works with session renewal', function () {
    Cache::flush();

    Http::fake([
        '*Login*' => Http::sequence()
            ->push(['SessionId' => 'initial'], 200, ['Set-Cookie' => 'B1SESSION=initial_cookie;'])
            ->push(['SessionId' => 'renewed'], 200, ['Set-Cookie' => 'B1SESSION=renewed_cookie;']),
        '*Warehouses*' => Http::sequence()
            ->push('Unauthorized', 401)
            ->push(['value' => [['WarehouseCode' => 'WH01']]], 200),
        '*' => Http::response('Not Found', 404),
    ]);

    // Using facade should also support automatic session renewal
    $response = SapB1::get('Warehouses');

    expect($response->successful())->toBeTrue();
    expect($response->json('value.0.WarehouseCode'))->toBe('WH01');
});

it('stores and sends ROUTEID cookie for sticky sessions', function () {
    Cache::flush();

    Http::fake([
        '*Login*' => Http::response(
            ['SessionId' => 'sticky_id'],
            200,
            ['Set-Cookie' => ['B1SESSION=sticky_session; path=/; HttpOnly', 'ROUTEID=.node5; path=/']]
        ),
        '*Items*' => Http::response(['value' => []], 200),
        '*' => Http::response('Not Found', 404),
    ]);

    // Force index 0 for predictability
    $client = new SapB1Client([], 0);
    $client->get('Items');

    // Verify session cache contains both B1SESSION and ROUTEID
    $sessionKey = 'sapb1-session:'.md5('https://sap-server/b1s/v1/SBO_PRODmanager'). ':0';
    $cachedCookie = Cache::get($sessionKey);

    expect($cachedCookie)->toContain('B1SESSION=sticky_session');
    expect($cachedCookie)->toContain('ROUTEID=.node5');

    // Verify ROUTEID is sent in subsequent requests
    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Items')) {
            $cookieHeader = $request->header('Cookie')[0] ?? '';

            return str_contains($cookieHeader, 'ROUTEID=.node5');
        }

        return true;
    });
});
