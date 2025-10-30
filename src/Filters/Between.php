<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

class Between extends Filter
{
    private string $field;

    private mixed $fromValue;

    private mixed $toValue;

    public function __construct(string $field, mixed $fromValue, mixed $toValue)
    {
        $this->field = $field;
        $this->fromValue = $fromValue;
        $this->toValue = $toValue;
    }

    #[Override]
    public function execute(): string
    {
        return '(' . $this->field . ' ge ' . $this->escape($this->fromValue) . ' and ' . $this->field . ' le ' . $this->escape($this->toValue) . ')';
    }
}
