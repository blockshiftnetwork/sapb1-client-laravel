<?php

namespace BlockshiftNetwork\SapB1Client\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class SapB1ClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock the SAP B1 Service Layer responses
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
    }

    /** @test */
    public function it_can_login_and_cache_the_session()
    {
        $this->assertFalse(Cache::has('sapb1-session:' . md5('https://sap-server/b1s/v1/SBO_PRODmanager')));

        Http::SapBOne([]);

        $this->assertTrue(Cache::has('sapb1-session:' . md5('https://sap-server/b1s/v1/SBO_PRODmanager')));
        $this->assertEquals('B1SESSION=mock_session_cookie;', Cache::get('sapb1-session:' . md5('https://sap-server/b1s/v1/SBO_PRODmanager')));
    }

    /** @test */
    public function it_throws_an_exception_on_failed_login()
    {
        Http::SapBOne([]); // First login is successful and caches the session
        Cache::flush(); // Flush the cache to force a new login

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("SAP B1 Login Failed: Login failed");

        Http::SapBOne([]); // This login should fail
    }

    /** @test */
    public function it_can_make_get_requests()
    {
        $response = Http::SapBOne([])->get('Items');
        $this->assertEquals([['ItemCode' => 'A001'], ['ItemCode' => 'A002']], $response->json('value'));
    }

    /** @test */
    public function it_can_make_odata_queries()
    {
        $response = Http::SapBOne([])->odataQuery('Items', ['$top' => 2]);
        $this->assertEquals([['ItemCode' => 'A001'], ['ItemCode' => 'A002']], $response->json('value'));
    }

    /** @test */
    public function it_can_make_post_requests()
    {
        $response = Http::SapBOne([])->post('BusinessPartners', ['CardCode' => 'C2024']);
        $this->assertEquals(['CardCode' => 'C2024'], $response->json());
    }

    /** @test */
    public function it_can_make_patch_requests()
    {
        $response = Http::SapBOne([])->patch("BusinessPartners('C2024')", ['CardName' => 'New Name']);
        $this->assertTrue($response->successful());
    }

    /** @test */
    public function it_can_make_delete_requests()
    {
        $response = Http::SapBOne([])->delete("BusinessPartners('C2024')");
        $this->assertTrue($response->successful());
    }

    /** @test */
    public function it_can_add_custom_headers_to_a_request()
    {
        Http::fake(['*' => Http::response(null, 200)]);

        Http::SapBOne([])->withHeaders(['X-Custom-Header' => 'CustomValue'])->get('Items');

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Custom-Header', 'CustomValue');
        });
    }

    /** @test */
    public function custom_headers_are_only_applied_to_the_next_request()
    {
        Http::fake(['*' => Http::response(null, 200)]);

        Http::SapBOne([])->withHeaders(['X-Custom-Header' => 'CustomValue'])->get('Items');
        Http::SapBOne([])->get('Items');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sap-server/b1s/v1/Items' && $request->hasHeader('X-Custom-Header', 'CustomValue');
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sap-server/b1s/v1/Items' && !$request->hasHeader('X-Custom-Header');
        });
    }

    /** @test */
    public function it_can_logout_and_clear_the_session()
    {
        Http::SapBOne([]);
        $this->assertTrue(Cache::has('sapb1-session:' . md5('https://sap-server/b1s/v1/SBO_PRODmanager')));

        Http::SapBOne([])->logout();

        $this->assertFalse(Cache::has('sapb1-session:' . md5('https://sap-server/b1s/v1/SBO_PRODmanager')));
    }
}
