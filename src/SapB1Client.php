<?php

namespace BlockshiftNetwork\SapB1Client;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;

class SapB1Client
{
    protected PendingRequest $http;

    protected array $config;

    protected ?string $sessionCookie = null;

    protected array $headers = [];

    protected bool $isRetryingWithNewSession = false;

    public function __construct(#[SensitiveParameter] array $config = [])
    {
        $this->config = array_merge(config('sapb1-client', []), $config);

        // Validar configuración antes de hacer cualquier cosa
        $this->validateConfig();

        $this->http = Http::withOptions([
            'base_uri' => $this->config['server'],
            'verify' => $this->config['verify_ssl'] ?? true,
        ])->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $this->login();
    }

    /**
     * Validate that required configuration values are present.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateConfig(): void
    {
        $required = ['server', 'database', 'username', 'password'];

        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                throw new \InvalidArgumentException("Missing required configuration: {$key}");
            }
        }
    }

    protected function getSessionKey(): string
    {
        return 'sapb1-session:' . md5($this->config['server'] . $this->config['database'] . $this->config['username']);
    }

    protected function login(): void
    {
        $sessionKey = $this->getSessionKey();

        if (Cache::has($sessionKey)) {
            $this->sessionCookie = Cache::get($sessionKey);

            return;
        }

        $this->performLogin();
    }

    /**
     * Perform the actual login request to SAP B1.
     * Cache::put() will automatically overwrite any existing session.
     */
    protected function performLogin(): void
    {
        $response = $this->http->retry(3, 100)
            ->post('Login', [
                'CompanyDB' => $this->config['database'],
                'UserName' => $this->config['username'],
                'Password' => $this->config['password'],
            ]);

        if ($response->failed()) {
            throw new Exception('SAP B1 Login Failed: ' . $response->body());
        }

        $this->sessionCookie = $response->header('Set-Cookie');
        $sessionKey = $this->getSessionKey();
        Cache::put($sessionKey, $this->sessionCookie, $this->config['cache_ttl']);
    }

    /**
     * Check if the response indicates an expired or invalid session.
     */
    protected function isSessionExpired(Response $response): bool
    {
        // SAP B1 returns 401 Unauthorized when session expires
        if ($response->status() === 401) {
            return true;
        }

        // Some SAP B1 configurations may return 403 Forbidden
        if ($response->status() === 403) {
            return true;
        }

        return false;
    }

    public function logout(): void
    {
        $sessionKey = $this->getSessionKey();

        if (Cache::has($sessionKey)) {
            $this->http->withHeaders(['Cookie' => $this->sessionCookie])->post('Logout');
            Cache::forget($sessionKey);
        }
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    private function sendRequest(string $method, string $endpoint, array $data = []): Response
    {
        // Crear una nueva instancia para evitar acumulación de headers
        $request = Http::withOptions([
            'base_uri' => $this->config['server'],
            'verify' => $this->config['verify_ssl'],
        ])->withHeaders(array_merge(
            [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Cookie' => $this->sessionCookie,
            ],
            $this->headers
        ));

        $this->headers = [];

        // Soportar todos los métodos HTTP
        $response = match ($method) {
            'get' => $request->get($endpoint, $data),
            'post' => $request->post($endpoint, $data),
            'put' => $request->put($endpoint, $data),
            'patch' => $request->patch($endpoint, $data),
            'delete' => $request->delete($endpoint, $data),
            default => $request->$method($endpoint, $data),
        };

        // Automatic session renewal on expiration (Octane-safe)
        if ($this->isSessionExpired($response) && ! $this->isRetryingWithNewSession) {
            $this->isRetryingWithNewSession = true;

            try {
                $this->performLogin();
                $response = $this->sendRequest($method, $endpoint, $data);
            } finally {
                $this->isRetryingWithNewSession = false;
            }
        }

        return $response;
    }

    public function get(string $endpoint, array $query = []): Response
    {
        return $this->sendRequest('get', $endpoint, $query);
    }

    public function post(string $endpoint, array $data = []): Response
    {
        return $this->sendRequest('post', $endpoint, $data);
    }

    public function put(string $endpoint, array $data = []): Response
    {
        return $this->sendRequest('put', $endpoint, $data);
    }

    public function patch(string $endpoint, array $data = []): Response
    {
        return $this->sendRequest('patch', $endpoint, $data);
    }

    public function delete(string $endpoint): Response
    {
        return $this->sendRequest('delete', $endpoint);
    }

    public function odataQuery(string $entity, array|ODataQuery $options = []): Response
    {
        if ($options instanceof ODataQuery) {
            $options = $options->toArray();
        }

        return $this->get($entity, $options);
    }

    public function sendRequestWithCallback(callable $callback): Response
    {
        // Usar this->http para que Http::fake() funcione correctamente en tests
        // pero agregamos headers de manera temporal
        $customHeaders = $this->headers;
        $this->headers = [];

        // Crear un request temporal con todos los headers necesarios
        $request = $this->http
            ->withHeaders(['Cookie' => $this->sessionCookie])
            ->withHeaders($customHeaders);

        $response = $callback($request);

        return $response;
    }

    /**
     * Execute multiple requests concurrently using Laravel's HTTP pool.
     *
     * @param  callable  $callback  Callback that receives a configured pool helper
     * @return array<int|string, Response>
     *
     * @example
     * ```php
     * $responses = SapB1::pool(function ($pool) {
     *     return [
     *         $pool->as('items')->get('Items', ['$top' => 5]),
     *         $pool->as('partners')->get('BusinessPartners', ['$top' => 5]),
     *         $pool->as('orders')->get('Orders', ['$top' => 5]),
     *     ];
     * });
     *
     * $items = $responses['items']->json('value');
     * $partners = $responses['partners']->json('value');
     * ```
     */
    public function pool(callable $callback): array
    {
        if (empty($this->sessionCookie)) {
            $this->login();
        }

        $sessionCookie = $this->sessionCookie;
        $config = $this->config;

        return Http::pool(function (Pool $pool) use ($callback, $sessionCookie, $config) {
            // Crear un helper simple que configura cada request del pool
            $poolHelper = new class($pool, $sessionCookie, $config)
            {
                public function __construct(
                    private Pool $pool,
                    private string $sessionCookie,
                    private array $config,
                    private ?string $currentKey = null
                ) {}

                private function configureRequest($poolOrRequest)
                {
                    return $poolOrRequest
                        ->withOptions([
                            'base_uri' => $this->config['server'],
                            'verify' => $this->config['verify_ssl'],
                        ])
                        ->withHeaders([
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                            'Cookie' => $this->sessionCookie,
                        ]);
                }

                public function as(string $key): self
                {
                    $this->currentKey = $key;

                    return $this;
                }

                public function __call(string $method, array $arguments)
                {
                    // Obtener el pool base (con o sin key)
                    $base = $this->currentKey !== null
                        ? $this->pool->as($this->currentKey)
                        : $this->pool;

                    // Limpiar el key
                    $this->currentKey = null;

                    // Configurar y ejecutar el método
                    return $this->configureRequest($base)->$method(...$arguments);
                }
            };

            return $callback($poolHelper);
        });
    }
}
