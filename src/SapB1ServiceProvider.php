<?php

namespace BlockshiftNetwork\SapB1Client;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\Events\RequestReceived;
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
        $this->app->singleton(SapB1Manager::class, function ($app): SapB1Manager {
            return new SapB1Manager($app['config']['sapb1-client']);
        });

        $this->app->alias(SapB1Manager::class, 'sapb1');
    }

    #[Override]
    public function bootingPackage(): void
    {
        Http::macro('SapB1', function (#[SensitiveParameter] array $config = []): SapB1Client {
            return new SapB1Client($config);
        });

        // Register connection shorthand macros
        SapB1Manager::macro('gateway', fn () => $this->connection('gateway'));
        SapB1Manager::macro('serviceLayer', fn () => $this->connection('service_layer'));

        // Always configure Octane listeners if Octane is available
        $this->configureOctane();
    }

    /**
     * Configure Laravel Octane state management for long-running processes.
     */
    protected function configureOctane(): void
    {
        if (class_exists('\Laravel\Octane\Events\RequestReceived')) {
            Event::listen(RequestReceived::class, function () {
                app(SapB1Manager::class)->resetAllForNewRequest();
            });
        }
    }
}
