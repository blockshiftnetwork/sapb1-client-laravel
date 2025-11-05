<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * Strict "less than" comparison (`field lt value`).
 */
class LessThan extends Filter
{
    private string $field;

    private mixed $value;

    /**
     * @param  string  $field  Field name to compare.
     * @param  mixed   $value  Upper bound that the field must be lower than.
     */
    public function __construct(string $field, mixed $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    #[Override]
    public function execute(): string
    {
        return $this->field . ' lt ' . $this->escape($this->value);
    }
}
