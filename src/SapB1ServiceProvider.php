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
        // Use scoped binding instead of singleton for Octane compatibility
        // This ensures each request gets a fresh instance in long-running processes
        $this->app->scoped(SapB1Client::class, function ($app): SapB1Client {
            return new SapB1Client;
        });
    }

    #[Override]
    public function bootingPackage(): void
    {
        Http::macro('SapB1', function (#[SensitiveParameter] array $config = []): SapB1Client {
            return new SapB1Client($config);
        });

        // Register Octane state cleanup listeners
        if ($this->app->resolved('octane')) {
            $this->configureOctane();
        }
    }

    /**
     * Configure Laravel Octane state management for long-running processes.
     */
    protected function configureOctane(): void
    {
        // Flush resolved instances between requests to prevent state leakage
        if (class_exists(\Laravel\Octane\Octane::class)) {
            \Laravel\Octane\Octane::flushState([
                SapB1Client::class,
            ]);
        }
    }
}
