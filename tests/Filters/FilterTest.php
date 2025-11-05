<?php

use BlockshiftNetwork\SapB1Client\Filters\Between;
use BlockshiftNetwork\SapB1Client\Filters\Contains;
use BlockshiftNetwork\SapB1Client\Filters\EndsWith;
use BlockshiftNetwork\SapB1Client\Filters\Equal;
use BlockshiftNetwork\SapB1Client\Filters\InArray;
use BlockshiftNetwork\SapB1Client\Filters\LessThan;
use BlockshiftNetwork\SapB1Client\Filters\LessThanEqual;
use BlockshiftNetwork\SapB1Client\Filters\MoreThan;
use BlockshiftNetwork\SapB1Client\Filters\MoreThanEqual;
use BlockshiftNetwork\SapB1Client\Filters\NotEqual;
use BlockshiftNetwork\SapB1Client\Filters\NotInArray;
use BlockshiftNetwork\SapB1Client\Filters\Raw;
use BlockshiftNetwork\SapB1Client\Filters\StartsWith;

it('renders an equal filter expression', function () {
    $filter = new Equal('CardCode', 'C001');

    expect($filter->execute())->toBe("CardCode eq 'C001'");
});

it('escapes string values in equal filter', function () {
    $filter = new Equal('CardName', "O'Brien Ltd");

    expect($filter->execute())->toBe("CardName eq 'O''Brien Ltd'");
});

it('renders a not equal filter expression', function () {
    $filter = new NotEqual('Status', 'Inactive');

    expect($filter->execute())->toBe("Status ne 'Inactive'");
});

it('renders greater than and greater than or equal filters', function () {
    expect((new MoreThan('Balance', 1000))->execute())->toBe('Balance gt 1000');
    expect((new MoreThanEqual('Stock', 10))->execute())->toBe('Stock ge 10');
});

it('renders less than and less than or equal filters', function () {
    expect((new LessThan('Discount', 15))->execute())->toBe('Discount lt 15');
    expect((new LessThanEqual('Age', 21))->execute())->toBe('Age le 21');
});

it('renders an in array filter expression', function () {
    $filter = new InArray('GroupCode', [1, 2, 3]);

    expect($filter->execute())->toBe('(GroupCode eq 1 or GroupCode eq 2 or GroupCode eq 3)');
});

it('renders a not in array filter expression', function () {
    $filter = new NotInArray('Country', ['US', 'CA']);

    expect($filter->execute())->toBe("(Country ne 'US' and Country ne 'CA')");
});

it('renders a between filter expression', function () {
    $filter = new Between('DocDate', '2024-01-01', '2024-01-31');

    expect($filter->execute())->toBe("(DocDate ge '2024-01-01' and DocDate le '2024-01-31')");
});

it('renders string function filters', function () {
    expect((new Contains('CardName', 'Corp'))->execute())->toBe("contains(CardName, 'Corp')");
    expect((new StartsWith('ItemCode', 'A'))->execute())->toBe("startswith(ItemCode, 'A')");
    expect((new EndsWith('Address', 'USA'))->execute())->toBe("endswith(Address, 'USA')");
});

it('returns the raw filter string without modification', function () {
    $filter = new Raw("contains(CardName, 'VIP') and Balance gt 1000");

    expect($filter->execute())->toBe("contains(CardName, 'VIP') and Balance gt 1000");
});

it('escapes literal values consistently', function () {
    $filter = new Equal('Dummy', 'value');

    expect($filter->escape(42))->toBe(42);
    expect($filter->escape(9.5))->toBe(9.5);
    expect($filter->escape("O'Brien"))->toBe("'O''Brien'");
    expect($filter->escape(true))->toBe('1');
});

it('quotes string values within in array filters', function () {
    $filter = new InArray('Status', ['Open', 'Closed']);

    expect($filter->execute())->toBe("(Status eq 'Open' or Status eq 'Closed')");
});