<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * Inclusive "greater than or equal" comparison (`field ge value`).
 */
class MoreThanEqual extends Filter
{
    private string $field;

    private mixed $value;

    /**
     * @param  string  $field  Field name to compare.
     * @param  mixed   $value  Minimum value allowed for the field.
     */
    public function __construct(string $field, mixed $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    #[Override]
    public function execute(): string
    {
        return $this->field . ' ge ' . $this->escape($this->value);
    }
}
