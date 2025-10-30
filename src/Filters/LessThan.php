<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

class LessThan extends Filter
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
        return $this->field.' lt '.$this->escape($this->value);
    }
}
