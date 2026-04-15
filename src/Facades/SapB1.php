<?php

namespace BlockshiftNetwork\SapB1Client\Facades;

use BlockshiftNetwork\SapB1Client\SapB1Manager;
use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @see SapB1Manager
 */
class SapB1 extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return SapB1Manager::class;
    }
}
