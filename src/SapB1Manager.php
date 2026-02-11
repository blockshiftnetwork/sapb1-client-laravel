<?php

namespace BlockshiftNetwork\SapB1Client;

use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;

class SapB1Manager
{
    use Macroable {
        __call as macroCall;
    }

    /** @var array<string, SapB1Client> */
    protected array $clients = [];

    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get a SAP B1 client instance for the given connection name.
     *
     * @throws InvalidArgumentException
     */
    public function connection(?string $name = null): SapB1Client
    {
        $name = $name ?? $this->getDefaultConnection();

        if (! isset($this->clients[$name])) {
            $connectionConfig = $this->config['connections'][$name] ?? null;

            if (! $connectionConfig) {
                throw new InvalidArgumentException("SAP B1 connection [{$name}] not configured.");
            }

            // Select random session index for load balancing
            $poolSize = (int) ($connectionConfig['pool_size'] ?? 1);
            $index = ($poolSize > 1) ? rand(0, $poolSize - 1) : 0;

            $this->clients[$name] = new SapB1Client($connectionConfig, $index);
        }

        return $this->clients[$name];
    }

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'service_layer';
    }

    /**
     * Reset request-specific state for all cached client instances.
     * Used by Octane to reset state between requests.
     */
    public function resetAllForNewRequest(): void
    {
        foreach ($this->clients as $client) {
            $client->resetForNewRequest();
        }
    }

    /**
     * Remove a cached client instance, or all instances if no name is given.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->clients = [];
        } else {
            unset($this->clients[$name]);
        }
    }

    /**
     * Get all cached client instances.
     *
     * @return array<string, SapB1Client>
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * Proxy method calls to macros first, then to the default connection.
     *
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        return $this->connection($this->getDefaultConnection())->$method(...$parameters);
    }
}
