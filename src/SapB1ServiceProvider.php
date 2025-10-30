<?php

namespace BlockshiftNetwork\SapB1Client;

use Illuminate\Support\Facades\Http;
use Override;
use SensitiveParameter;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SapB1ServiceProvider extends PackageServiceProvider
{
    #[Override]
    public function configurePackage(Package $package): void
    {
        $package
            ->name('sapb1-client')
            ->hasConfigFile();
    }

    #[Override]
    public function registeringPackage(): void
    {
        $this->app->singleton(SapB1Client::class, function ($app): SapB1Client {
            return new SapB1Client;
        });
    }

    #[Override]
    public function bootingPackage(): void
    {
        Http::macro('SapBOne', function (#[SensitiveParameter] array $config = []): SapB1Client {
            return new SapB1Client($config);
        });
    }
}
