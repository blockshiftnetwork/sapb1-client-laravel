<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

/**
 * Represents an inclusive "between" comparison for an OData filter.
 *
 * The generated expression looks like
 * `(Field ge FromValue and Field le ToValue)`.
 */
class Between extends Filter
{
    private string $field;

    private mixed $fromValue;

    private mixed $toValue;

    /**
     * @param  string  $field  Field name to compare.
     * @param  mixed  $fromValue  Inclusive lower bound.
     * @param  mixed  $toValue  Inclusive upper bound.
     */
    public function __construct(string $field, mixed $fromValue, mixed $toValue)
    {
        $this->field = $field;
        $this->fromValue = $fromValue;
        $this->toValue = $toValue;
    }

    #[Override]
    public function execute(): string
    {
        return '('.$this->field.' ge '.$this->escape($this->fromValue).' and '.$this->field.' le '.$this->escape($this->toValue).')';
    }
}
