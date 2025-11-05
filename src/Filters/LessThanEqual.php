<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * Inclusive "less than or equal" comparison (`field le value`).
 */
class LessThanEqual extends Filter
{
    private string $field;

    private mixed $value;

    /**
     * @param  string  $field  Field name to compare.
     * @param  mixed  $value  Maximum value allowed for the field.
     */
    public function __construct(string $field, mixed $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    #[Override]
    public function execute(): string
    {
        return $this->field.' le '.$this->escape($this->value);
    }
}
