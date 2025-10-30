<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

use Override;

class Raw extends Filter
{
    private string $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    #[Override]
    public function execute(): string
    {
        return $this->string;
    }
}
