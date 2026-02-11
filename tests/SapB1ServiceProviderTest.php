<?php

use BlockshiftNetwork\SapB1Client\SapB1Manager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config()->set('sapb1-client', [
        'default' => 'service_layer',
        'connections' => [
            'service_layer' => [
                'server' => 'https://sap-server/b1s/v1/',
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

it('registers sap b1 manager as singleton', function () {
    Http::fake([
        '*Login*' => sapLoginHttpResponse('https://sap-server/b1s/v1/', 'singleton_session', 'singleton_cookie'),
    ]);

    $firstManager = app(SapB1Manager::class);
    $secondManager = app(SapB1Manager::class);

    expect($firstManager)->toBe($secondManager);

    // Accessing a connection should create the client and login
    $client1 = $firstManager->connection('service_layer');
    $client2 = $secondManager->connection('service_layer');

    expect($client1)->toBe($client2);

    $loginCount = collect(Http::recorded())
        ->filter(fn ($record) => str_contains($record[0]->url(), 'Login'))
        ->count();

    expect($loginCount)->toBe(1);
});

it('registers sapb1 alias', function () {
    $fromClass = app(SapB1Manager::class);
    $fromAlias = app('sapb1');

    expect($fromClass)->toBe($fromAlias);
});

it('http macro respects per-call configuration overrides', function () {
    Http::fake([
        '*Login*' => sapLoginHttpResponse('https://custom-server/b1s/v1/', 'macro_session', 'macro_cookie'),
        '*Orders*' => Http::response(['value' => []], 200),
    ]);

    $client = Http::SapB1([
        'server' => 'https://custom-server/b1s/v1/',
        'database' => 'CUSTOM_DB',
        'username' => 'custom_user',
        'password' => 'custom_pass',
    ]);

    $response = $client->get('Orders');

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'Login')) {
            return false;
        }

        return str_contains($request->url(), 'custom-server');
    });

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'Orders')) {
            return false;
        }

        return str_contains($request->url(), 'custom-server');
    });

    $sessionKey = 'sapb1-session:'.md5('https://custom-server/b1s/v1/CUSTOM_DBcustom_user').':0';

    expect(Cache::has($sessionKey))->toBeTrue();
});
