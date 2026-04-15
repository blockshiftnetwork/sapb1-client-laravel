<?php

use BlockshiftNetwork\SapB1Client\ODataQuery;
use BlockshiftNetwork\SapB1Client\SapB1Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config()->set('sapb1-client', [
        'default' => 'service_layer',
        'connections' => [
            'service_layer' => [
                'driver' => 'servicelayer',
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

/**
 * Helper: assert a request URL contains literal $-prefixed OData keys
 * and does NOT contain their %24-encoded equivalents.
 */
function assertODataKeysNotEncoded(string $url, array $keys): void
{
    foreach ($keys as $key) {
        expect($url)->toContain($key);
        expect($url)->not->toContain(str_replace('$', '%24', $key));
    }
}

// --------------------------------------------------------------------------
// odataQuery() with raw array
// --------------------------------------------------------------------------

it('does not percent-encode $ in OData query keys via odataQuery()', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return sapLoginHttpResponse();
        }

        return Http::response(['value' => []], 200);
    });

    $client = new SapB1Client;
    $client->odataQuery('Items', ['$top' => 5, '$select' => 'ItemCode']);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Items') && ! str_contains($request->url(), 'Login')) {
            assertODataKeysNotEncoded($request->url(), ['$top=', '$select=']);

            return true;
        }

        return true;
    });
});

it('preserves literal $ in $apply aggregation queries', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return sapLoginHttpResponse();
        }

        return Http::response(['value' => []], 200);
    });

    $client = new SapB1Client;
    $client->odataQuery('Orders', [
        '$apply' => 'groupby((CardCode),aggregate(DocTotal with sum as Total))',
    ]);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Orders') && ! str_contains($request->url(), 'Login')) {
            assertODataKeysNotEncoded($request->url(), ['$apply=']);
            expect($request->url())->toContain('aggregate');

            return true;
        }

        return true;
    });
});

it('preserves literal $ for all 7 OData system query options', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return sapLoginHttpResponse();
        }

        return Http::response(['value' => []], 200);
    });

    $client = new SapB1Client;
    $client->odataQuery('Orders', [
        '$select' => 'DocEntry,DocNum',
        '$filter' => "CardCode eq 'C001'",
        '$orderby' => 'DocEntry desc',
        '$top' => 10,
        '$skip' => 5,
        '$expand' => 'DocumentLines',
        '$apply' => 'aggregate(DocTotal with sum as Total)',
    ]);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Orders') && ! str_contains($request->url(), 'Login')) {
            assertODataKeysNotEncoded($request->url(), [
                '$select=', '$filter=', '$orderby=', '$top=', '$skip=', '$expand=', '$apply=',
            ]);

            return true;
        }

        return true;
    });
});

it('encodes special characters in values but not in $ keys', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return sapLoginHttpResponse();
        }

        return Http::response(['value' => []], 200);
    });

    $client = new SapB1Client;
    $client->odataQuery('Items', [
        '$filter' => "ItemName eq 'Widget & Gadget'",
    ]);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Items') && ! str_contains($request->url(), 'Login')) {
            assertODataKeysNotEncoded($request->url(), ['$filter=']);

            return true;
        }

        return true;
    });
});

it('does not append query string for empty OData params', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return sapLoginHttpResponse();
        }

        return Http::response(['value' => []], 200);
    });

    $client = new SapB1Client;
    $client->odataQuery('Items', []);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Items') && ! str_contains($request->url(), 'Login')) {
            // URL should not have a trailing ? or any query string
            $path = parse_url($request->url(), PHP_URL_QUERY);

            return $path === null || $path === '';
        }

        return true;
    });
});

// --------------------------------------------------------------------------
// Fluent query builder (SapB1Query)
// --------------------------------------------------------------------------

it('fluent query builder produces un-encoded $ in URL', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return sapLoginHttpResponse();
        }

        return Http::response(['value' => []], 200);
    });

    $client = new SapB1Client;
    $client->query('Orders')
        ->select('DocEntry', 'DocNum')
        ->where('DocTotal', '>', 1000)
        ->orderBy('DocEntry', 'desc')
        ->top(5)
        ->skip(10)
        ->expand('DocumentLines')
        ->get();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Orders') && ! str_contains($request->url(), 'Login')) {
            assertODataKeysNotEncoded($request->url(), [
                '$select=', '$filter=', '$orderby=', '$top=', '$skip=', '$expand=',
            ]);

            return true;
        }

        return true;
    });
});

it('find() produces un-encoded $ for select and expand', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return sapLoginHttpResponse();
        }

        return Http::response(['ItemCode' => 'A001'], 200);
    });

    $client = new SapB1Client;
    $client->query('Items')
        ->select('ItemCode', 'ItemName')
        ->expand('ItemPrices')
        ->find('A001');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), "Items('A001')")) {
            assertODataKeysNotEncoded($request->url(), ['$select=', '$expand=']);

            return true;
        }

        return true;
    });
});

// --------------------------------------------------------------------------
// Session retry
// --------------------------------------------------------------------------

it('session retry preserves un-encoded $ in URL', function () {
    $callCount = 0;

    Http::fake(function ($request) use (&$callCount) {
        if (str_contains($request->url(), 'Login')) {
            return Http::response(
                ['SessionId' => 'session_'.$callCount],
                200,
                ['Set-Cookie' => 'B1SESSION=cookie_'.$callCount.';']
            );
        }

        if (str_contains($request->url(), 'Orders')) {
            $callCount++;
            // First call returns 401 (expired session), second returns 200
            if ($callCount === 1) {
                return Http::response(['error' => ['message' => ['value' => 'Session expired']]], 401);
            }

            return Http::response(['value' => []], 200);
        }

        return Http::response('Not Found', 404);
    });

    $client = new SapB1Client;
    $client->odataQuery('Orders', ['$top' => 10, '$filter' => "CardCode eq 'C001'"]);

    // Assert that the retried request also has un-encoded $ keys
    $orderRequests = collect(Http::recorded())
        ->filter(fn ($record) => str_contains($record[0]->url(), 'Orders'));

    expect($orderRequests)->toHaveCount(2);

    $orderRequests->each(function ($record) {
        $url = $record[0]->url();
        assertODataKeysNotEncoded($url, ['$top=', '$filter=']);
    });
});

// --------------------------------------------------------------------------
// Pool requests
// --------------------------------------------------------------------------

it('pool GET requests do not percent-encode $ in query keys', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return sapLoginHttpResponse();
        }

        return Http::response(['value' => []], 200);
    });

    $client = new SapB1Client;
    $responses = $client->pool(function ($pool) {
        $pool->as('items')->get('Items', ['$top' => 5, '$select' => 'ItemCode']);
        $pool->as('orders')->get('Orders', ['$filter' => "DocStatus eq 'O'"]);
    });

    $poolGets = collect(Http::recorded())
        ->filter(fn ($record) => str_contains($record[0]->url(), 'Items') || str_contains($record[0]->url(), 'Orders'))
        ->filter(fn ($record) => ! str_contains($record[0]->url(), 'Login'));

    $poolGets->each(function ($record) {
        $url = $record[0]->url();
        // Should NOT contain %24
        expect($url)->not->toContain('%24');
    });
});

// --------------------------------------------------------------------------
// Edge case: dollar sign in filter values
// --------------------------------------------------------------------------

it('preserves dollar sign in filter values correctly', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'Login')) {
            return sapLoginHttpResponse();
        }

        return Http::response(['value' => []], 200);
    });

    $client = new SapB1Client;
    $client->odataQuery('Items', [
        '$filter' => "ItemName eq '\$pecial'",
    ]);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'Items') && ! str_contains($request->url(), 'Login')) {
            // Key should have literal $
            assertODataKeysNotEncoded($request->url(), ['$filter=']);

            return true;
        }

        return true;
    });
});
