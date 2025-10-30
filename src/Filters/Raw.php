<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

class Raw extends Filter
{
    private string $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function execute(): string
    {
        return $this->string;
    }
}
