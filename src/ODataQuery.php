<?php

namespace BlockshiftNetwork\SapB1Client;

use BlockshiftNetwork\SapB1Client\Filters\Filter;

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

    public function where(Filter $filter)
    {
        $this->filter[] = $filter;

        return $this;
    }

    public function orderBy($field, $direction = 'asc')
    {
        $this->orderBy[] = $field.' '.$direction;

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
        if (! empty($this->select)) {
            $query['$select'] = implode(',', $this->select);
        }
        if (! empty($this->filter)) {
            $query['$filter'] = $this->compileFilters();
        }
        if (! empty($this->orderBy)) {
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
        $filterParts = [];
        foreach ($this->filter as $filter) {
            $filterParts[] = $filter->execute();
        }

        return implode(' and ', $filterParts);
    }
}
