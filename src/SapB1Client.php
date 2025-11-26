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

    protected int $sessionIndex = 0;

    public function __construct(#[SensitiveParameter] array $config = [], ?int $sessionIndex = null)
    {
        $this->config = array_merge(config('sapb1-client', []), $config);
        
        // Set session index (Priority: Constructor -> Config -> Default 0)
        if ($sessionIndex !== null) {
            $this->sessionIndex = $sessionIndex;
        } elseif (isset($this->config['session_index'])) {
            $this->sessionIndex = (int) $this->config['session_index'];
        } else {
            // Random selection if pool enabled and no specific index requested
            $poolSize = (int) ($this->config['pool_size'] ?? 1);
            if ($poolSize > 1) {
                $this->sessionIndex = rand(0, $poolSize - 1);
            }
        }

        // Validate configuration before doing anything
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
        $baseKey = 'sapb1-session:'.md5($this->config['server'].$this->config['database'].$this->config['username']);
        
        // Append index to support multiple sessions in the pool
        return "{$baseKey}:{$this->sessionIndex}";
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
        // Avoid infinite retry loops if login itself fails
        $response = $this->http->retry(3, 100)
            ->post('Login', [
                'CompanyDB' => $this->config['database'],
                'UserName' => $this->config['username'],
                'Password' => $this->config['password'],
            ]);

        if ($response->failed()) {
            throw new Exception("SAP B1 Login Failed (Index: {$this->sessionIndex}): ".$response->body());
        }

        // Get cookies from response
        $cookies = $response->cookies();
        $cookieParts = [];
        
        // Extract raw cookie string or reconstruct it properly
        // The Set-Cookie header might be an array or string
        // But the most reliable way for subsequent requests is using the CookieJar or reconstruction
        
        // Simple reconstruction for the header
        foreach ($cookies as $cookie) {
            $cookieParts[] = $cookie->getName() . '=' . $cookie->getValue();
        }
        
        $this->sessionCookie = implode('; ', $cookieParts);

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
        // Create a new instance to avoid header accumulation
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

        // Support all HTTP methods
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
                // Force clear cache for THIS specific index
                Cache::forget($this->getSessionKey());
                
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
        // Use this->http so Http::fake() works correctly in tests
        // but we add headers temporarily
        $customHeaders = $this->headers;
        $this->headers = [];

        // Create a temporary request with all necessary headers
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
     */
    public function pool(callable $callback): array
    {
        if (empty($this->sessionCookie)) {
            $this->login();
        }

        $sessionCookie = $this->sessionCookie;
        $config = $this->config;

        return Http::pool(function (Pool $pool) use ($callback, $sessionCookie, $config) {
            // Create a simple helper that configures each request in the pool
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
                    // Get base pool (with or without key)
                    $base = $this->currentKey !== null
                        ? $this->pool->as($this->currentKey)
                        : $this->pool;

                    // Clear key
                    $this->currentKey = null;

                    // Configure and execute method
                    return $this->configureRequest($base)->$method(...$arguments);
                }
            };

            return $callback($poolHelper);
        });
    }
}
