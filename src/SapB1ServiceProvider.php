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
        // We use the container binding to hook into Octane's events if needed,
        // but since we use scoped(), Laravel Octane handles it automatically.
        // However, if we wanted to force flush static states, we'd do it here.
        // For now, scoped binding is sufficient.
    }
}
