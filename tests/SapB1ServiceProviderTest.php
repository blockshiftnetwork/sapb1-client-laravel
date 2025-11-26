<?php

use BlockshiftNetwork\SapB1Client\SapB1Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config()->set('sapb1-client.server', 'https://sap-server/b1s/v1/');
    config()->set('sapb1-client.database', 'SBO_PROD');
    config()->set('sapb1-client.username', 'manager');
    config()->set('sapb1-client.password', 'password');
    config()->set('sapb1-client.cache_ttl', 1800);
    config()->set('sapb1-client.verify_ssl', false);
});

it('registers sap b1 client as singleton', function () {
    Http::fake([
        '*Login*' => sapLoginHttpResponse('https://sap-server/b1s/v1/', 'singleton_session', 'singleton_cookie'),
    ]);

    $firstInstance = app(SapB1Client::class);
    $secondInstance = app(SapB1Client::class);

    expect($firstInstance)->toBe($secondInstance);

    $loginCount = collect(Http::recorded())
        ->filter(fn ($record) => str_contains($record[0]->url(), 'Login'))
        ->count();

    expect($loginCount)->toBe(1);
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
