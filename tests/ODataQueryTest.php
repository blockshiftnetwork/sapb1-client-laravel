<?php

use BlockshiftNetwork\SapB1Client\Filters\Raw;
use BlockshiftNetwork\SapB1Client\ODataQuery;

describe('ODataQuery - Select', function () {
    it('can build a select query with multiple fields', function () {
        $query = (new ODataQuery)->select('CardCode', 'CardName');
        expect($query->toArray())->toBe(['$select' => 'CardCode,CardName']);
    });

    it('can build a select query with array', function () {
        $query = (new ODataQuery)->select(['ItemCode', 'ItemName', 'Price']);
        expect($query->toArray())->toBe(['$select' => 'ItemCode,ItemName,Price']);
    });
});

describe('ODataQuery - Where Operators', function () {
    it('can build a simple where clause with implicit eq', function () {
        $query = (new ODataQuery)->where('CardCode', 'C001');
        expect($query->toArray())->toBe(['$filter' => "CardCode eq 'C001'"]);
    });

    it('can build a where clause with explicit = operator', function () {
        $query = (new ODataQuery)->where('CardType', '=', 'cCustomer');
        expect($query->toArray())->toBe(['$filter' => "CardType eq 'cCustomer'"]);
    });

    it('can build a where clause with eq operator', function () {
        $query = (new ODataQuery)->where('Status', 'eq', 'Active');
        expect($query->toArray())->toBe(['$filter' => "Status eq 'Active'"]);
    });

    it('can use not equal operator', function () {
        $query = (new ODataQuery)->where('Status', '!=', 'Inactive');
        expect($query->toArray())->toBe(['$filter' => "Status ne 'Inactive'"]);
    });

    it('can use ne operator', function () {
        $query = (new ODataQuery)->where('CardType', 'ne', 'cLead');
        expect($query->toArray())->toBe(['$filter' => "CardType ne 'cLead'"]);
    });

    it('can use greater than operator', function () {
        $query = (new ODataQuery)->where('Total', '>', 1000);
        expect($query->toArray())->toBe(['$filter' => 'Total gt 1000']);
    });

    it('can use gt operator', function () {
        $query = (new ODataQuery)->where('Balance', 'gt', 500);
        expect($query->toArray())->toBe(['$filter' => 'Balance gt 500']);
    });

    it('can use greater than or equal operator', function () {
        $query = (new ODataQuery)->where('Stock', '>=', 10);
        expect($query->toArray())->toBe(['$filter' => 'Stock ge 10']);
    });

    it('can use ge operator', function () {
        $query = (new ODataQuery)->where('Quantity', 'ge', 1);
        expect($query->toArray())->toBe(['$filter' => 'Quantity ge 1']);
    });

    it('can use less than operator', function () {
        $query = (new ODataQuery)->where('Discount', '<', 20);
        expect($query->toArray())->toBe(['$filter' => 'Discount lt 20']);
    });

    it('can use lt operator', function () {
        $query = (new ODataQuery)->where('Price', 'lt', 100);
        expect($query->toArray())->toBe(['$filter' => 'Price lt 100']);
    });

    it('can use less than or equal operator', function () {
        $query = (new ODataQuery)->where('Age', '<=', 65);
        expect($query->toArray())->toBe(['$filter' => 'Age le 65']);
    });

    it('can use le operator', function () {
        $query = (new ODataQuery)->where('MaxQuantity', 'le', 999);
        expect($query->toArray())->toBe(['$filter' => 'MaxQuantity le 999']);
    });
});

describe('ODataQuery - String Functions', function () {
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
});

describe('ODataQuery - Array Operators', function () {
    it('can use where in', function () {
        $query = (new ODataQuery)->where('CardCode', 'in', ['C001', 'C002']);
        expect($query->toArray())->toBe(['$filter' => "(CardCode eq 'C001' or CardCode eq 'C002')"]);
    });

    it('can use where not in', function () {
        $query = (new ODataQuery)->where('CardCode', 'notin', ['C001', 'C002']);
        expect($query->toArray())->toBe(['$filter' => "(CardCode ne 'C001' and CardCode ne 'C002')"]);
    });

    it('can use where between', function () {
        $query = (new ODataQuery)->where('DocDate', 'between', ['2024-01-01', '2024-01-31']);
        expect($query->toArray())->toBe(['$filter' => "(DocDate ge '2024-01-01' and DocDate le '2024-01-31')"]);
    });
});

describe('ODataQuery - Chaining', function () {
    it('can chain multiple where clauses', function () {
        $query = (new ODataQuery)
            ->where('CardType', 'cCustomer')
            ->where('Balance', '>', 0);
        expect($query->toArray())->toBe(['$filter' => "CardType eq 'cCustomer' and Balance gt 0"]);
    });

    it('can chain where and orWhere clauses', function () {
        $query = (new ODataQuery)
            ->where('CardType', 'cCustomer')
            ->orWhere('CardType', 'cSupplier');
        expect($query->toArray())->toBe(['$filter' => "CardType eq 'cCustomer' or CardType eq 'cSupplier'"]);
    });

    it('can chain multiple orWhere clauses', function () {
        $query = (new ODataQuery)
            ->where('Status', 'Active')
            ->orWhere('Status', 'Pending')
            ->orWhere('Status', 'Review');
        expect($query->toArray())->toBe(['$filter' => "Status eq 'Active' or Status eq 'Pending' or Status eq 'Review'"]);
    });

    it('can mix where and orWhere with different operators', function () {
        $query = (new ODataQuery)
            ->where('CardType', 'cCustomer')
            ->where('Balance', '>', 0)
            ->orWhere('CardName', 'contains', 'VIP');
        expect($query->toArray())->toBe(['$filter' => "CardType eq 'cCustomer' and Balance gt 0 or contains(CardName, 'VIP')"]);
    });
});

describe('ODataQuery - OrderBy', function () {
    it('can build an order by clause with default asc', function () {
        $query = (new ODataQuery)->orderBy('CardCode');
        expect($query->toArray())->toBe(['$orderby' => 'CardCode asc']);
    });

    it('can build an order by clause with desc', function () {
        $query = (new ODataQuery)->orderBy('DocDate', 'desc');
        expect($query->toArray())->toBe(['$orderby' => 'DocDate desc']);
    });

    it('can order by multiple fields', function () {
        $query = (new ODataQuery)
            ->orderBy('CardType', 'asc')
            ->orderBy('CardName', 'desc');
        expect($query->toArray())->toBe(['$orderby' => 'CardType asc,CardName desc']);
    });
});

describe('ODataQuery - Pagination', function () {
    it('can set the top limit', function () {
        $query = (new ODataQuery)->top(10);
        expect($query->toArray())->toBe(['$top' => 10]);
    });

    it('can set the skip offset', function () {
        $query = (new ODataQuery)->skip(20);
        expect($query->toArray())->toBe(['$skip' => 20]);
    });

    it('can combine top and skip for pagination', function () {
        $query = (new ODataQuery)->top(25)->skip(50);
        expect($query->toArray())->toBe(['$top' => 25, '$skip' => 50]);
    });
});

describe('ODataQuery - Complex Queries', function () {
    it('can build a complex query with all features', function () {
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

    it('can build query with multiple filters and sorting', function () {
        $query = (new ODataQuery)
            ->select('DocEntry', 'DocNum', 'CardCode', 'DocTotal')
            ->where('DocTotal', '>=', 1000)
            ->where('DocDate', '>=', '2024-01-01')
            ->where('Cancelled', 'tNO')
            ->orderBy('DocNum', 'desc')
            ->top(50);

        $result = $query->toArray();

        expect($result)->toHaveKey('$select');
        expect($result)->toHaveKey('$filter');
        expect($result)->toHaveKey('$orderby');
        expect($result)->toHaveKey('$top');
        expect($result['$top'])->toBe(50);
    });
});

describe('ODataQuery - Raw Filters', function () {
    it('can use a raw filter', function () {
        $rawFilter = new Raw("contains(CardName, 'Test') and Balance gt 100");
        $query = (new ODataQuery)->where($rawFilter);

        expect($query->toArray())->toBe([
            '$filter' => "contains(CardName, 'Test') and Balance gt 100",
        ]);
    });

    it('can mix raw filter with regular filters', function () {
        $rawFilter = new Raw("substring(ItemCode, 1, 1) eq 'A'");
        $query = (new ODataQuery)
            ->where('ItemType', 'itItems')
            ->where($rawFilter);

        $result = $query->toArray();
        expect($result['$filter'])->toContain("ItemType eq 'itItems'");
        expect($result['$filter'])->toContain("substring(ItemCode, 1, 1) eq 'A'");
    });
});

describe('ODataQuery - Edge Cases', function () {
    it('handles single quotes in string values', function () {
        $query = (new ODataQuery)->where('CardName', "O'Brien Corporation");
        expect($query->toArray())->toBe(['$filter' => "CardName eq 'O''Brien Corporation'"]);
    });

    it('handles numeric values without quotes', function () {
        $query = (new ODataQuery)
            ->where('Quantity', 100)
            ->where('Price', 99.99);

        $result = $query->toArray();
        expect($result['$filter'])->toBe('Quantity eq 100 and Price eq 99.99');
    });

    it('throws exception for between with invalid array', function () {
        expect(fn() => (new ODataQuery)->where('DocDate', 'between', ['2024-01-01']))
            ->toThrow(InvalidArgumentException::class, 'The value for "between" operator must be an array of two elements');
    });

    it('throws exception for unsupported operator', function () {
        expect(fn() => (new ODataQuery)->where('Field', 'invalid_operator', 'value'))
            ->toThrow(InvalidArgumentException::class, "Unsupported operator 'invalid_operator'");
    });
});

describe('ODataQuery - Method Chaining', function () {
    it('returns self for method chaining', function () {
        $query = new ODataQuery();

        expect($query->select('Field1'))->toBe($query);
        expect($query->where('Field2', 'value'))->toBe($query);
        expect($query->orderBy('Field3'))->toBe($query);
        expect($query->top(10))->toBe($query);
        expect($query->skip(5))->toBe($query);
    });

    it('can chain all methods in any order', function () {
        $query = (new ODataQuery)
            ->top(20)
            ->select('Field1', 'Field2')
            ->skip(10)
            ->where('Field1', '>', 0)
            ->orderBy('Field2', 'desc')
            ->where('Field3', 'Active');

        $result = $query->toArray();

        expect($result)->toHaveKey('$select');
        expect($result)->toHaveKey('$filter');
        expect($result)->toHaveKey('$orderby');
        expect($result)->toHaveKey('$top');
        expect($result)->toHaveKey('$skip');
    });
});
