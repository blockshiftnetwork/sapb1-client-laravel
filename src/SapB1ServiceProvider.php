<?php

namespace BlockshiftNetwork\SapB1Client;

use Illuminate\Support\Facades\Event;
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
        // Use singleton for performance (instance reuse across requests)
        // Session state is managed via cache (not instance properties)
        // Request-specific state (headers) is reset between requests
        $this->app->singleton(SapB1Client::class, function (): SapB1Client {
            // Load pool size from config
            $poolSize = (int) config('sapb1-client.pool_size');

            // Select random index for load balancing
            $index = ($poolSize > 1) ? rand(0, $poolSize - 1) : 0;

            return new SapB1Client([], $index);
        });
    }

    #[Override]
    public function bootingPackage(): void
    {
        Http::macro('SapB1', function (#[SensitiveParameter] array $config = []): SapB1Client {
            return new SapB1Client($config);
        });

        // Always configure Octane listeners if Octane is available
        $this->configureOctane();
    }

    protected function registerResponseMacros(): void
    {
        Response::macro('value', function (mixed $default = null): mixed {
            return $this->json('value', $default);
        });

        Response::macro('first', function (mixed $default = null): mixed {
            return ((object) [...$this->json('value')[0]]) ?? $default;
        });
    }

    /**
     * Configure Laravel Octane state management for long-running processes.
     */
    protected function configureOctane(): void
    {
        if (class_exists('\Laravel\Octane\Events\RequestReceived')) {
            Event::listen(\Laravel\Octane\Events\RequestReceived::class, function () {
                // Reset request-specific state at the start of each request
                $client = app(SapB1Client::class);
                $client->resetForNewRequest();
            });
        }
    }
}
