<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * Simple equality comparison (`field eq value`).
 */
class Equal extends Filter
{
    private string $field;

    private mixed $value;

    /**
     * @param  string  $field  Field name to compare.
     * @param  mixed  $value  Value that the field must equal.
     */
    public function __construct(string $field, mixed $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    #[Override]
    public function execute(): string
    {
        return $this->field.' eq '.$this->escape($this->value);
    }
}
