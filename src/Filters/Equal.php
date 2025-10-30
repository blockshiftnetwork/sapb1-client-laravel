<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

class Equal extends Filter
{
    private string $field;

    private mixed $value;

    public function __construct(string $field, mixed $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    #[Override]
    public function execute(): string
    {
        return $this->field.' eq '.$this->escape($this->value);
    }
}
