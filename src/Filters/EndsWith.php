<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * OData `endswith` function filter for suffix matching on a field value.
 */
class EndsWith extends Filter
{
    private string $field;

    private mixed $value;

    /**
     * @param  string  $field  Field name to evaluate.
     * @param  mixed  $value  Suffix that must be present at the end of the field value.
     */
    public function __construct(string $field, mixed $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    #[Override]
    public function execute(): string
    {
        return 'endswith('.$this->field.', '.$this->escape($this->value).')';
    }
}
