<?php

namespace BlockshiftNetwork\SapB1Client\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use BlockshiftNetwork\SapB1Client\SapB1ServiceProvider;

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
