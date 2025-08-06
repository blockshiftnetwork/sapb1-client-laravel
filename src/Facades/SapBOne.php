<?php

namespace BlockshiftNetwork\SapB1Client\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \BlockshiftNetwork\SapB1Client\SapB1Client
 */
class SapBOne extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \BlockshiftNetwork\SapB1Client\SapB1Client::class;
    }
}
