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

### B. Custom SML Request Example

```php
// Custom SML endpoint, custom headers
$response = SapBOne::withHeaders(['X-Company-Context' => 'VENEZUELA'])
    ->get('/sml.svc/ItemsWithStock', [
      'warehouse'=> 'CABUDARE01'
    ]);

$data = $response->json();
```

### C. POST, PATCH, DELETE Requests

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

### D. Explicit Logout

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
