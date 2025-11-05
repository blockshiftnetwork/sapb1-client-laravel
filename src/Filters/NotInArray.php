<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * Logical negation counterpart of {@see InArray}: ensures the field does not
 * match any of the provided values.
 */
class NotInArray extends Filter
{
    private string $field;

    /** @var array<int, mixed> */
    private array $collection;

    /**
     * @param  string            $field       Field name to compare.
     * @param  array<int, mixed> $collection  Values that the field must differ from.
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
            $op = ($idx < count($this->collection) - 1) ? ' and ' : '';
            $group .= $this->field . ' ne ' . $this->escape($value) . $op;
        }

        return '(' . $group . ')';
    }
}
