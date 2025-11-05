<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * OData equivalent to a SQL `IN` clause: generates `(field eq value1 or field eq value2 ...)`.
 */
class InArray extends Filter
{
    private string $field;

    /** @var array<int, mixed> */
    private array $collection;

    /**
     * @param  string  $field  Field name to compare.
     * @param  array<int, mixed>  $collection  Values that should match the field.
     */
    public function __construct(string $field, array $collection)
    {
        $this->field = $field;
        $this->collection = $collection;
    }

    #[Override]
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
