# SAP B1 Client for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blockshiftnetwork/sapb1-client.svg?style=flat-square)](https://packagist.org/packages/blockshiftnetwork/sapb1-client)
[![Total Downloads](https://img.shields.io/packagist/dt/blockshiftnetwork/sapb1-client.svg?style=flat-square)](https://packagist.org/packages/blockshiftnetwork/sapb1-client)

A robust, production-grade Laravel package for SAP Business One HTTP integration, supporting both OData and custom (non-OData) SAP Service Layer (SML) endpoints.

## Installation

You can install the package via composer:

```bash
composer require blockshiftnetwork/sapb1-client
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="sapb1-client-config"
```

This is the contents of the published config file:

```php
return [
    'server'     => env('SAPB1_SERVER'),
    'database'   => env('SAPB1_DATABASE'),
    'username'   => env('SAPB1_USERNAME'),
    'password'   => env('SAPB1_PASSWORD'),
    'cache_ttl'  => env('SAPB1_CACHE_TTL', 1800),
    'verify_ssl' => env('SAPB1_VERIFY_SSL', true),
];
```

## Usage

### A. Typical OData Example

First, make sure to configure your SAP B1 credentials in your `.env` file.

```php
use BlockshiftNetwork\SapB1Client\Facades\SapBOne;

// Query top 5 items
$response = SapBOne::odataQuery('Items', [
  '$filter'  => "ItemsGroupCode eq 100",
  '$orderby' => "ItemCode desc",
  '$top'     => 5,
]);

$items = $response->json('value');
```

### B. Advanced OData Queries with the Query Builder

For more complex scenarios, you can use the `ODataQuery` builder to construct your queries in a readable and maintainable way, very similar to Laravel's Eloquent.

```php
use BlockshiftNetwork\SapB1Client\Facades\SapBOne;
use BlockshiftNetwork\SapB1Client\ODataQuery;

$query = (new ODataQuery())
    ->select('CardCode', 'CardName', 'Balance')
    ->where('CardType', '=', 'cCustomer') // ->where('CardType', 'cCustomer') also works
    ->orWhere('CardName', 'contains', 'Acme Inc.')
    ->where('Balance', '>', 0)
    ->where('CreateDate', 'between', ['2023-01-01', '2023-12-31'])
    ->orderBy('CardName', 'desc')
    ->top(50)
    ->skip(10);

$response = SapBOne::odataQuery('BusinessPartners', $query);

$customers = $response->json('value');
```

#### Supported Operators

The `where` and `orWhere` methods support a variety of operators:

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

For very specific or complex filters that are not covered by the operators above, you can still pass a `Filter` instance directly: `->where(new Raw("substring(CardName, 1, 3) eq 'ABC'"))`

### C. Custom SML Request Example

```php
// Custom SML endpoint, custom headers
$response = SapBOne::withHeaders(['X-Company-Context' => 'VENEZUELA'])
    ->get('/sml.svc/ItemsWithStock', [
      'warehouse'=> 'CABUDARE01'
    ]);

$data = $response->json();
```

### D. File Uploads and Custom Requests

For scenarios like file uploads or other complex requests that are not covered by the standard methods, you can use the `sendRequestWithCallback` method. This gives you direct access to the configured HTTP client instance while still benefiting from the automatic re-login logic.

```php
use BlockshiftNetwork\SapB1Client\Facades\SapBOne;

$response = SapBOne::sendRequestWithCallback(function ($httpClient) {
    return $httpClient
        ->attach('my_file', file_get_contents('/path/to/file.pdf'), 'file.pdf')
        ->post('Attachments2');
});
```

### E. POST, PATCH, DELETE Requests

```php
// POST example
$newCustomer = [
    'CardCode' => 'C2024',
    'CardName' => 'Beta Tech VZLA'
];
$created = SapBOne::post('BusinessPartners', $newCustomer);

// PATCH example
$body = ['CardName' => 'New Beta Tech'];
$update = SapBOne::patch("BusinessPartners('C2024')", $body);

// DELETE example
$delete = SapBOne::delete("BusinessPartners('C2024')");
```

### F. Concurrent Requests with Pool

Execute multiple requests concurrently for better performance:

```php
use BlockshiftNetwork\SapB1Client\Facades\SapBOne;

$responses = SapBOne::pool(function ($pool) {
    return [
        $pool->as('items')->get('Items', ['$top' => 10]),
        $pool->as('partners')->get('BusinessPartners', ['$top' => 10]),
        $pool->as('warehouses')->get('Warehouses'),
        $pool->as('orders')->get('Orders', ['$top' => 5]),
    ];
});

// Access responses by their keys
$items = $responses['items']->json('value');
$partners = $responses['partners']->json('value');
$warehouses = $responses['warehouses']->json('value');
$orders = $responses['orders']->json('value');
```

You can also use POST, PUT, PATCH, DELETE in pool:

```php
$responses = SapBOne::pool(function ($pool) {
    return [
        $pool->as('create')->post('BusinessPartners', ['CardCode' => 'C001', 'CardName' => 'New Customer']),
        $pool->as('update')->patch("Items('A001')", ['ItemName' => 'Updated Item']),
        $pool->as('fetch')->get('Orders', ['$top' => 1]),
    ];
});
```

### G. Explicit Logout

```php
SapBOne::logout();
```

## Testing

```bash
composer test
```

## Credits

- [Blockshift Network](https://github.com/blockshiftnetwork)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
