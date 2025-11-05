<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * Strict "greater than" comparison (`field gt value`).
 */
class MoreThan extends Filter
{
    private string $field;

    private mixed $value;

    /**
     * @param  string  $field  Field name to compare.
     * @param  mixed  $value  Lower bound that the field must exceed.
     */
    public function __construct(string $field, mixed $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    #[Override]
    public function execute(): string
    {
        return $this->field.' gt '.$this->escape($this->value);
    }
}
