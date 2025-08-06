<?php

namespace BlockshiftNetwork\SapB1Client\Tests;

use BlockshiftNetwork\SapB1Client\SapB1ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            SapB1ServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
