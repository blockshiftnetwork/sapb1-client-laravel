<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * Not-equal comparison (`field ne value`).
 */
class NotEqual extends Filter
{
    private string $field;

    private mixed $value;

    /**
     * @param  string  $field  Field name to compare.
     * @param  mixed   $value  Value that the field must differ from.
     */
    public function __construct(string $field, mixed $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    #[Override]
    public function execute(): string
    {
        return $this->field . ' ne ' . $this->escape($this->value);
    }
}
