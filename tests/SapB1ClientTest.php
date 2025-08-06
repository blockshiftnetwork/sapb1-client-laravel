<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'sap-server/b1s/v1/Login' => Http::sequence()
            ->push(['SessionId' => 'mock_session_id', 'Version' => '10.0'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;'])
            ->push('Login failed', 500)
            ->push(['SessionId' => 'new_mock_session_id', 'Version' => '10.0'], 200, ['Set-Cookie' => 'B1SESSION=new_mock_session_cookie;']),
        'sap-server/b1s/v1/Items*' => Http::response(['value' => [['ItemCode' => 'A001'], ['ItemCode' => 'A002']]], 200),
        'sap-server/b1s/v1/BusinessPartners' => Http::sequence()
            ->push(['CardCode' => 'C2024'], 201)
            ->push(null, 204),
        'sap-server/b1s/v1/BusinessPartners(\'C2024\')' => Http::sequence()
            ->push(null, 204)
            ->push(null, 204),
        'sap-server/b1s/v1/Logout' => Http::response(null, 204),
        '*' => Http::response('Not Found', 404),
    ]);

    config()->set('sapb1-client.server', 'https://sap-server/b1s/v1/');
    config()->set('sapb1-client.database', 'SBO_PROD');
    config()->set('sapb1-client.username', 'manager');
    config()->set('sapb1-client.password', 'password');
});

it('can login and cache the session', function () {
    $this->assertFalse(Cache::has('sapb1-session:'.md5('https://sap-server/b1s/v1/SBO_PRODmanager')));

    Http::SapBOne([]);

    $this->assertTrue(Cache::has('sapb1-session:'.md5('https://sap-server/b1s/v1/SBO_PRODmanager')));
    $this->assertEquals('B1SESSION=mock_session_cookie;', Cache::get('sapb1-session:'.md5('https://sap-server/b1s/v1/SBO_PRODmanager')));
});

it('throws an exception on failed login', function () {
    Http::SapBOne([]);
    Cache::flush();

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('SAP B1 Login Failed: Login failed');

    Http::SapBOne([]);
});

it('can make get requests', function () {
    $response = Http::SapBOne([])->get('Items');
    $this->assertEquals([['ItemCode' => 'A001'], ['ItemCode' => 'A002']], $response->json('value'));
});

it('can make odata queries', function () {
    $response = Http::SapBOne([])->odataQuery('Items', ['$top' => 2]);
    $this->assertEquals([['ItemCode' => 'A001'], ['ItemCode' => 'A002']], $response->json('value'));
});

it('can make post requests', function () {
    $response = Http::SapBOne([])->post('BusinessPartners', ['CardCode' => 'C2024']);
    $this->assertEquals(['CardCode' => 'C2024'], $response->json());
});

it('can make patch requests', function () {
    $response = Http::SapBOne([])->patch("BusinessPartners('C2024')", ['CardName' => 'New Name']);
    $this->assertTrue($response->successful());
});

it('can make delete requests', function () {
    $response = Http::SapBOne([])->delete("BusinessPartners('C2024')");
    $this->assertTrue($response->successful());
});

it('can add custom headers to a request', function () {
    Http::fake(['*' => Http::response(null, 200)]);

    Http::SapBOne([])->withHeaders(['X-Custom-Header' => 'CustomValue'])->get('Items');

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Custom-Header', 'CustomValue');
    });
});

it('custom headers are only applied to the next request', function () {
    Http::fake(['*' => Http::response(null, 200)]);

    Http::SapBOne([])->withHeaders(['X-Custom-Header' => 'CustomValue'])->get('Items');
    Http::SapBOne([])->get('Items');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://sap-server/b1s/v1/Items' && $request->hasHeader('X-Custom-Header', 'CustomValue');
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'https://sap-server/b1s/v1/Items' && ! $request->hasHeader('X-Custom-Header');
    });
});

it('can logout and clear the session', function () {
    Http::SapBOne([]);
    $this->assertTrue(Cache::has('sapb1-session:'.md5('https://sap-server/b1s/v1/SBO_PRODmanager')));

    Http::SapBOne([])->logout();

    $this->assertFalse(Cache::has('sapb1-session:'.md5('https://sap-server/b1s/v1/SBO_PRODmanager')));
});

it('retries the request on failure', function () {
    Http::fake([
        'sap-server/b1s/v1/Login' => Http::response(['SessionId' => 'mock_session_id'], 200, ['Set-Cookie' => 'B1SESSION=mock_session_cookie;']),
        'sap-server/b1s/v1/Items' => Http::sequence()
            ->push('Server Error', 500)
            ->push(['value' => [['ItemCode' => 'A001']]], 200),
    ]);

    $response = Http::SapBOne([])->get('Items');

    $this->assertTrue($response->successful());
    $this->assertEquals([['ItemCode' => 'A001']], $response->json('value'));
});
