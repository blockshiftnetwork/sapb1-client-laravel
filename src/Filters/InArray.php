<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

class InArray extends Filter
{
    private string $field;

    /** @var array<int, mixed> */
    private array $collection;

    /**
     * @param  array<int, mixed>  $collection
     */
    public function __construct(string $field, array $collection)
    {
        $this->field = $field;
        $this->collection = $collection;
    }

    public function execute(): string
    {
        $group = '';

        foreach ($this->collection as $idx => $value) {
            $op = ($idx < count($this->collection) - 1) ? ' or ' : '';
            $group .= $this->field.' eq '.$this->escape($value).$op;
        }

        return '('.$group.')';
    }
}
