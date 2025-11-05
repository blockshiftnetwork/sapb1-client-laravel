<?php

namespace BlockshiftNetwork\SapB1Client\Facades;

use BlockshiftNetwork\SapB1Client\SapB1Client;
use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @see \BlockshiftNetwork\SapB1Client\SapB1Client
 */
class SapB1 extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return SapB1Client::class;
    }
}
