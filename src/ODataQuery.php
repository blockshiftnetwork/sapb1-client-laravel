<?php

namespace BlockshiftNetwork\SapB1Client;

use BlockshiftNetwork\SapB1Client\Filters\Between;
use BlockshiftNetwork\SapB1Client\Filters\Contains;
use BlockshiftNetwork\SapB1Client\Filters\EndsWith;
use BlockshiftNetwork\SapB1Client\Filters\Equal;
use BlockshiftNetwork\SapB1Client\Filters\Filter;
use BlockshiftNetwork\SapB1Client\Filters\InArray;
use BlockshiftNetwork\SapB1Client\Filters\LessThan;
use BlockshiftNetwork\SapB1Client\Filters\LessThanEqual;
use BlockshiftNetwork\SapB1Client\Filters\MoreThan;
use BlockshiftNetwork\SapB1Client\Filters\MoreThanEqual;
use BlockshiftNetwork\SapB1Client\Filters\NotEqual;
use BlockshiftNetwork\SapB1Client\Filters\NotInArray;
use BlockshiftNetwork\SapB1Client\Filters\StartsWith;
use InvalidArgumentException;

class ODataQuery
{
    private $select = [];
    private $filter = [];
    private $orderBy = [];
    private $top;
    private $skip;

    public function select($fields)
    {
        $this->select = is_array($fields) ? $fields : func_get_args();
        return $this;
    }

    public function where($field, $operator = null, $value = null)
    {
        if ($field instanceof Filter) {
            if (!empty($this->filter)) {
                $field->setOperator('and');
            }
            $this->filter[] = $field;
            return $this;
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = 'eq';
        }

        $filter = $this->createFilter($field, $operator, $value);

        if (!empty($this->filter)) {
            $filter->setOperator('and');
        }

        $this->filter[] = $filter;

        return $this;
    }

    public function orWhere($field, $operator = null, $value = null)
    {
        if ($field instanceof Filter) {
            if (empty($this->filter)) {
                // An orWhere shouldn't be the first condition, but if it is, treat as `where`.
            } else {
                $field->setOperator('or');
            }
            $this->filter[] = $field;
            return $this;
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = 'eq';
        }

        $filter = $this->createFilter($field, $operator, $value);

        if (empty($this->filter)) {
            // orWhere as first clause doesn't make sense logically, but we'll allow it and it will just be a normal where.
        } else {
            $filter->setOperator('or');
        }
        
        $this->filter[] = $filter;

        return $this;
    }

    protected function createFilter($field, $operator, $value)
    {
        $operatorMap = [
            '=' => Equal::class,
            'eq' => Equal::class,
            '!=' => NotEqual::class,
            'ne' => NotEqual::class,
            '>' => MoreThan::class,
            'gt' => MoreThan::class,
            '>=' => MoreThanEqual::class,
            'ge' => MoreThanEqual::class,
            '<' => LessThan::class,
            'lt' => LessThan::class,
            '<=' => LessThanEqual::class,
            'le' => LessThanEqual::class,
            'contains' => Contains::class,
            'startswith' => StartsWith::class,
            'endswith' => EndsWith::class,
            'in' => InArray::class,
            'notin' => NotInArray::class,
        ];
        
        $operator = strtolower($operator);
        
        if ($operator === 'between') {
            if (!is_array($value) || count($value) !== 2) {
                throw new InvalidArgumentException('The value for "between" operator must be an array of two elements.');
            }
            return new Between($field, $value[0], $value[1]);
        }

        if (!isset($operatorMap[$operator])) {
            throw new InvalidArgumentException("Unsupported operator '{$operator}'");
        }

        $filterClass = $operatorMap[$operator];
        return new $filterClass($field, $value);
    }

    public function orderBy($field, $direction = 'asc')
    {
        $this->orderBy[] = $field . ' ' . $direction;
        return $this;
    }

    public function top($number)
    {
        $this->top = $number;
        return $this;
    }

    public function skip($number)
    {
        $this->skip = $number;
        return $this;
    }

    public function toArray()
    {
        $query = [];
        if (!empty($this->select)) {
            $query['$select'] = implode(',', $this->select);
        }
        if (!empty($this->filter)) {
            $query['$filter'] = $this->compileFilters();
        }
        if (!empty($this->orderBy)) {
            $query['$orderby'] = implode(',', $this->orderBy);
        }
        if (isset($this->top)) {
            $query['$top'] = $this->top;
        }
        if (isset($this->skip)) {
            $query['$skip'] = $this->skip;
        }
        return $query;
    }

    private function compileFilters()
    {
        $filterString = '';
        foreach ($this->filter as $index => $filter) {
            if ($index > 0) {
                $filterString .= ' ' . ($filter->getOperator() ?? 'and') . ' ';
            }
            $filterString .= $filter->execute();
        }
        return $filterString;
    }
}
