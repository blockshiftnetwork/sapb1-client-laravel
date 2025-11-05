<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * OData `contains` function filter for substring matching on a given field.
 */
class Contains extends Filter
{
    private string $field;

    private mixed $value;

    /**
     * @param  string  $field  Field name to apply the `contains` function on.
     * @param  mixed   $value  Substring to search for within the field value.
     */
    public function __construct(string $field, mixed $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    #[Override]
    public function execute(): string
    {
        return 'contains(' . $this->field . ', ' . $this->escape($this->value) . ')';
    }
}
