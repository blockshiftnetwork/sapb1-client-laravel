<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

class MoreThan extends Filter
{
    private string $field;

    private mixed $value;

    public function __construct(string $field, mixed $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    public function execute(): string
    {
        return $this->field.' gt '.$this->escape($this->value);
    }
}
