<?php

namespace BlockshiftNetwork\SapB1Client;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Illuminate\Support\Facades\Http;
use BlockshiftNetwork\SapB1Client\SapB1Client;

class SapB1ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('sapb1-client')
            ->hasConfigFile();
    }

    public function bootingPackage()
    {
        Http::macro('SapBOne', function (array $config) {
            return new SapB1Client($config);
        });
    }
}
