# SAP B1 Client for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blockshiftnetwork/sapb1-client.svg?style=flat-square)](https://packagist.org/packages/blockshiftnetwork/sapb1-client)
[![Total Downloads](https://img.shields.io/packagist/dt/blockshiftnetwork/sapb1-client.svg?style=flat-square)](https://packagist.org/packages/blockshiftnetwork/sapb1-client)

A robust, production-grade Laravel package for SAP Business One HTTP integration, supporting multiple connections, fluent OData queries, and both standard and custom Service Layer endpoints.

## Requirements

- PHP 8.3 or higher
- Laravel 11.x, 12.x, or 13.x

## Installation

You can install the package via composer:

```bash
composer require blockshiftnetwork/sapb1-client
```

Publish the config file:

```bash
php artisan vendor:publish --tag="sapb1-client-config"
```

## Configuration

The package supports multiple named connections, each with its own server, credentials, and isolated session management. Add your credentials to `.env`:

```env
# Default connection (optional, defaults to "service_layer")
SAPB1_DEFAULT_CONNECTION=service_layer

# Service Layer connection
SAPB1_SERVICE_LAYER_SERVER=https://your-sap-host.com/b1s/v1
SAPB1_SERVICE_LAYER_DATABASE=YOUR_COMPANY_DB
SAPB1_SERVICE_LAYER_USERNAME=manager
SAPB1_SERVICE_LAYER_PASSWORD=secret

# Gateway / Report Service connection
SAPB1_GATEWAY_SERVER=https://your-sap-gateway.com/rs/v1
SAPB1_GATEWAY_DATABASE=YOUR_COMPANY_DB
SAPB1_GATEWAY_USERNAME=manager
SAPB1_GATEWAY_PASSWORD=secret
```

The published config file (`config/sapb1-client.php`):

```php
return [
    'default' => env('SAPB1_DEFAULT_CONNECTION', 'service_layer'),

    'connections' => [
        'service_layer' => [
            'server'     => env('SAPB1_SERVICE_LAYER_SERVER'),
            'database'   => env('SAPB1_SERVICE_LAYER_DATABASE'),
            'username'   => env('SAPB1_SERVICE_LAYER_USERNAME'),
            'password'   => env('SAPB1_SERVICE_LAYER_PASSWORD'),
            'cache_ttl'  => env('SAPB1_SERVICE_LAYER_CACHE_TTL', 1800),
            'pool_size'  => env('SAPB1_SERVICE_LAYER_POOL_SIZE', 1),
            'verify_ssl' => env('SAPB1_SERVICE_LAYER_VERIFY_SSL', true),
        ],

        'gateway' => [
            'server'     => env('SAPB1_GATEWAY_SERVER'),
            'database'   => env('SAPB1_GATEWAY_DATABASE'),
            'username'   => env('SAPB1_GATEWAY_USERNAME'),
            'password'   => env('SAPB1_GATEWAY_PASSWORD'),
            'cache_ttl'  => env('SAPB1_GATEWAY_CACHE_TTL', 1800),
            'pool_size'  => env('SAPB1_GATEWAY_POOL_SIZE', 1),
            'verify_ssl' => env('SAPB1_GATEWAY_VERIFY_SSL', true),
        ],
    ],
];
```

You can add as many connections as you need. Each connection maintains its own session independently.

## Usage

### Multiple Connections

The package works like Laravel's `DB::connection()`. The `SapB1` facade resolves to a connection manager that lazily creates client instances per named connection.

```php
use BlockshiftNetwork\SapB1Client\Facades\SapB1;

// Use a specific connection
SapB1::connection('service_layer')->get('Items');
SapB1::connection('gateway')->post('PDFExport', ['DocEntry' => 1]);

// Shorthand macros (registered out of the box)
SapB1::serviceLayer()->get('Items');
SapB1::gateway()->post('PDFExport', ['DocEntry' => 1]);

// Calls without connection() go to the default connection
SapB1::get('Items');
```

### Fluent Query Builder

The `query()` method returns a fluent builder for full CRUD operations on any SAP entity.

#### Listing records

```php
$response = SapB1::query('BusinessPartners')
    ->select('CardCode', 'CardName', 'Balance')
    ->where('CardType', 'cCustomer')
    ->where('Balance', '>', 0)
    ->orderBy('CardName')
    ->top(50)
    ->skip(10)
    ->get();

$customers = $response->json('value');
```

#### Finding a single record

```php
// String key
$response = SapB1::query('BusinessPartners')->find('C001');

// Numeric key
$response = SapB1::query('Orders')->find(123);

// With select
$response = SapB1::query('BusinessPartners')
    ->select('CardCode', 'CardName')
    ->find('C001');
```

#### Creating a record

```php
$response = SapB1::query('BusinessPartners')->create([
    'CardCode' => 'C2024',
    'CardName' => 'Acme Corp',
    'CardType' => 'cCustomer',
]);
```

#### Updating a record

```php
$response = SapB1::query('BusinessPartners')->update('C2024', [
    'CardName' => 'Acme Corporation',
]);
```

When updating documents with collection properties like `DocumentLines`, use `replaceCollections: true` to send the `B1S-ReplaceCollectionsOnPatch` header. Without it, SAP merges lines instead of replacing them.

```php
$response = SapB1::query('Orders')->update(123, [
    'DocumentLines' => [
        ['ItemCode' => 'A001', 'Quantity' => 5],
        ['ItemCode' => 'A002', 'Quantity' => 3],
    ],
], replaceCollections: true);
```

#### Deleting a record

```php
SapB1::query('BusinessPartners')->delete('C2024');
SapB1::query('Orders')->delete(123);
```

#### Query builder on named connections

All query builder methods work on any connection:

```php
SapB1::gateway()->query('PDFExport')->create(['DocEntry' => 1]);

SapB1::serviceLayer()->query('Items')
    ->select('ItemCode', 'ItemName')
    ->where('ItemCode', 'startswith', 'A')
    ->top(20)
    ->get();
```

### OData Queries with Array Syntax

You can also pass raw OData parameters as an array:

```php
$response = SapB1::odataQuery('Items', [
    '$filter'  => "ItemsGroupCode eq 100",
    '$orderby' => "ItemCode desc",
    '$top'     => 5,
]);

$items = $response->json('value');
```

Or build them with the standalone `ODataQuery` class:

```php
use BlockshiftNetwork\SapB1Client\ODataQuery;

$query = (new ODataQuery())
    ->select('CardCode', 'CardName', 'Balance')
    ->where('CardType', '=', 'cCustomer')
    ->orWhere('CardName', 'contains', 'Acme Inc.')
    ->where('Balance', '>', 0)
    ->where('CreateDate', 'between', ['2023-01-01', '2023-12-31'])
    ->orderBy('CardName', 'desc')
    ->top(50)
    ->skip(10);

$response = SapB1::odataQuery('BusinessPartners', $query);
```

#### Supported Operators

The `where` and `orWhere` methods support these operators:

| Operator     | Description           | Example                                                       |
| ------------ | --------------------- | ------------------------------------------------------------- |
| `=`, `eq`    | Equal                 | `->where('CardType', '=', 'cCustomer')`                       |
| `!=`, `ne`   | Not Equal             | `->where('Status', '!=', 'Inactive')`                         |
| `>`, `gt`    | Greater Than          | `->where('Balance', '>', 1000)`                               |
| `>=`, `ge`   | Greater Than or Equal | `->where('Stock', '>=', 10)`                                  |
| `<`, `lt`    | Less Than             | `->where('DocTotal', '<', 500)`                               |
| `<=`, `le`   | Less Than or Equal    | `->where('Discount', '<=', 15)`                               |
| `contains`   | String Contains       | `->where('CardName', 'contains', 'Shop')`                     |
| `startswith` | String Starts With    | `->where('ItemCode', 'startswith', 'A')`                      |
| `endswith`   | String Ends With      | `->where('Address', 'endswith', 'USA')`                       |
| `in`         | In Array              | `->where('GroupCode', 'in', [1, 2, 5])`                       |
| `notin`      | Not In Array          | `->where('Country', 'notin', ['US', 'CA'])`                   |
| `between`    | Between two values    | `->where('DocDate', 'between', ['2024-01-01', '2024-01-31'])` |

For very specific or complex filters not covered above, pass a `Filter` instance directly: `->where(new Raw("substring(CardName, 1, 3) eq 'ABC'"))`

### Custom Headers and SML Requests

```php
$response = SapB1::withHeaders(['X-Company-Context' => 'VENEZUELA'])
    ->get('/sml.svc/ItemsWithStock', [
        'warehouse' => 'CABUDARE01',
    ]);
```

### File Uploads and Custom Requests

For scenarios like file uploads or other complex requests, use `sendRequestWithCallback` for direct access to the configured HTTP client:

```php
$response = SapB1::sendRequestWithCallback(function ($httpClient) {
    return $httpClient
        ->attach('my_file', file_get_contents('/path/to/file.pdf'), 'file.pdf')
        ->post('Attachments2');
});
```

### Direct HTTP Methods

All standard HTTP methods are available on any connection:

```php
SapB1::post('BusinessPartners', ['CardCode' => 'C2024', 'CardName' => 'Beta Tech']);
SapB1::patch("BusinessPartners('C2024')", ['CardName' => 'New Beta Tech']);
SapB1::delete("BusinessPartners('C2024')");
```

### Concurrent Requests with Pool

Execute multiple requests concurrently for better performance:

```php
$responses = SapB1::pool(function ($pool) {
    return [
        $pool->as('items')->get('Items', ['$top' => 10]),
        $pool->as('partners')->get('BusinessPartners', ['$top' => 10]),
        $pool->as('warehouses')->get('Warehouses'),
        $pool->as('orders')->get('Orders', ['$top' => 5]),
    ];
});

$items = $responses['items']->json('value');
$partners = $responses['partners']->json('value');
```

You can also use POST, PUT, PATCH, DELETE in the pool:

```php
$responses = SapB1::pool(function ($pool) {
    return [
        $pool->as('create')->post('BusinessPartners', ['CardCode' => 'C001', 'CardName' => 'New Customer']),
        $pool->as('update')->patch("Items('A001')", ['ItemName' => 'Updated Item']),
        $pool->as('fetch')->get('Orders', ['$top' => 1]),
    ];
});
```

### Http Macro

You can also create a one-off client with explicit config using the `Http::SapB1()` macro:

```php
$client = Http::SapB1([
    'server'   => 'https://other-sap-host.com/b1s/v1',
    'database' => 'OTHER_DB',
    'username' => 'user',
    'password' => 'pass',
]);

$response = $client->get('Items');
```

### Explicit Logout

```php
SapB1::logout();

// Or on a specific connection
SapB1::connection('gateway')->logout();
```

## Laravel Octane Compatibility

The package is fully compatible with Laravel Octane. The `SapB1Manager` is registered as a singleton and automatically resets request-specific state (headers, retry flags) at the start of each Octane request. Session cookies are managed via Laravel's cache and are not affected by the reset.

## Testing

```bash
composer test
```

## Credits

- [Blockshift Network](https://github.com/blockshiftnetwork)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
