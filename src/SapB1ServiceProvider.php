<?php

namespace BlockshiftNetwork\SapB1Client;

use Illuminate\Support\Facades\Http;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
