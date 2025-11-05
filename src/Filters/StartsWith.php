<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * OData `startswith` function filter for prefix matching on a field value.
 */
class StartsWith extends Filter
{
    private string $field;

    private mixed $value;

    /**
     * @param  string  $field  Field name to evaluate.
     * @param  mixed   $value  Prefix that must be present at the start of the field value.
     */
    public function __construct(string $field, mixed $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    #[Override]
    public function execute(): string
    {
        return 'startswith(' . $this->field . ', ' . $this->escape($this->value) . ')';
    }
}
