<?php

use BlockshiftNetwork\SapB1Client\Filters\Raw;
use BlockshiftNetwork\SapB1Client\ODataQuery;

it('can build a select query', function () {
    $query = (new ODataQuery)->select('CardCode', 'CardName');
    expect($query->toArray())->toBe(['$select' => 'CardCode,CardName']);
});

it('can build a simple where clause', function () {
    $query = (new ODataQuery)->where('CardCode', 'C001');
    expect($query->toArray())->toBe(['$filter' => "CardCode eq 'C001'"]);
});

it('can build a where clause with a different operator', function () {
    $query = (new ODataQuery)->where('Total', '>', 1000);
    expect($query->toArray())->toBe(['$filter' => 'Total gt 1000']);
});

it('can chain where clauses', function () {
    $query = (new ODataQuery)
        ->where('CardType', 'cCustomer')
        ->where('Balance', '>', 0);
    expect($query->toArray())->toBe(['$filter' => "CardType eq 'cCustomer' and Balance gt 0"]);
});

it('can build an or where clause', function () {
    $query = (new ODataQuery)
        ->where('CardType', 'cCustomer')
        ->orWhere('CardType', 'cSupplier');
    expect($query->toArray())->toBe(['$filter' => "CardType eq 'cCustomer' or CardType eq 'cSupplier'"]);
});

it('can use a contains filter', function () {
    $query = (new ODataQuery)->where('CardName', 'contains', 'Corporation');
    expect($query->toArray())->toBe(['$filter' => "contains(CardName, 'Corporation')"]);
});

it('can use a startswith filter', function () {
    $query = (new ODataQuery)->where('ItemCode', 'startswith', 'A');
    expect($query->toArray())->toBe(['$filter' => "startswith(ItemCode, 'A')"]);
});

it('can use an endswith filter', function () {
    $query = (new ODataQuery)->where('ItemCode', 'endswith', 'Z');
    expect($query->toArray())->toBe(['$filter' => "endswith(ItemCode, 'Z')"]);
});

it('can build an order by clause', function () {
    $query = (new ODataQuery)->orderBy('CardCode');
    expect($query->toArray())->toBe(['$orderby' => 'CardCode asc']);
});

it('can build an order by clause with direction', function () {
    $query = (new ODataQuery)->orderBy('DocDate', 'desc');
    expect($query->toArray())->toBe(['$orderby' => 'DocDate desc']);
});

it('can set the top limit', function () {
    $query = (new ODataQuery)->top(10);
    expect($query->toArray())->toBe(['$top' => 10]);
});

it('can set the skip offset', function () {
    $query = (new ODataQuery)->skip(20);
    expect($query->toArray())->toBe(['$skip' => 20]);
});

it('can build a complex query', function () {
    $query = (new ODataQuery)
        ->select('CardCode', 'CardName', 'Balance')
        ->where('CardType', 'cCustomer')
        ->where('Balance', '>', 0)
        ->orderBy('CardName', 'asc')
        ->top(5)
        ->skip(10);

    expect($query->toArray())->toBe([
        '$select' => 'CardCode,CardName,Balance',
        '$filter' => "CardType eq 'cCustomer' and Balance gt 0",
        '$orderby' => 'CardName asc',
        '$top' => 5,
        '$skip' => 10,
    ]);
});

it('can use a raw filter', function () {
    $rawFilter = new Raw("contains(CardName, 'Test') and Balance gt 100");
    $query = (new ODataQuery)->where($rawFilter);

    expect($query->toArray())->toBe([
        '$filter' => "contains(CardName, 'Test') and Balance gt 100",
    ]);
});

it('can use where in', function () {
    $query = (new ODataQuery)->where('CardCode', 'in', ['C001', 'C002']);
    expect($query->toArray())->toBe(['$filter' => "CardCode eq 'C001' or CardCode eq 'C002'"]);
});

it('can use where not in', function () {
    $query = (new ODataQuery)->where('CardCode', 'notin', ['C001', 'C002']);
    expect($query->toArray())->toBe(['$filter' => "CardCode ne 'C001' and CardCode ne 'C002'"]);
});

it('can use where between', function () {
    $query = (new ODataQuery)->where('DocDate', 'between', ['2024-01-01', '2024-01-31']);
    expect($query->toArray())->toBe(['$filter' => "DocDate ge '2024-01-01' and DocDate le '2024-01-31'"]);
});
