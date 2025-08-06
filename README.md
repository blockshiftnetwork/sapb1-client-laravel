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

For more complex scenarios, you can use the `ODataQuery` builder to construct your queries in a more readable and maintainable way.

```php
use BlockshiftNetwork\SapB1Client\Facades\SapBOne;
use BlockshiftNetwork\SapB1Client\ODataQuery;
use BlockshiftNetwork\SapB1Client\Filters\Equal;
use BlockshiftNetwork\SapB1Client\Filters\Contains;

$query = (new ODataQuery())
    ->select('CardCode', 'CardName', 'Balance')
    ->where(new Equal('CardType', 'cCustomer'))
    ->where(new Contains('CardName', 'Parameter'))
    ->orderBy('CardName', 'desc')
    ->top(10)
    ->skip(5);

$response = SapBOne::odataQuery('BusinessPartners', $query);

$customers = $response->json('value');
```

#### Available Filters

The query builder supports a wide range of filters:

- `Between(string $field, mixed $fromValue, mixed $toValue)`
- `Contains(string $field, string $value)`
- `EndsWith(string $field, string $value)`
- `Equal(string $field, mixed $value)`
- `InArray(string $field, array $collection)`
- `LessThan(string $field, mixed $value)`
- `LessThanEqual(string $field, mixed $value)`
- `MoreThan(string $field, mixed $value)`
- `MoreThanEqual(string $field, mixed $value)`
- `NotEqual(string $field, mixed $value)`
- `NotInArray(string $field, array $collection)`
- `Raw(string $rawFilter)`
- `StartsWith(string $field, string $value)`

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

### F. Explicit Logout

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
