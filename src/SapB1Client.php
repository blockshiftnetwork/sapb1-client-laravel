<?php

namespace BlockshiftNetwork\SapB1Client;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SapB1Client
{
    protected PendingRequest $http;

    protected array $config;

    protected ?string $sessionCookie = null;

    protected array $headers = [];

    public function __construct(array $config = [])
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
        return 'sapb1-session:'.md5($this->config['server'].$this->config['database'].$this->config['username']);
    }

    protected function login(): void
    {
        $sessionKey = $this->getSessionKey();

        if (Cache::has($sessionKey)) {
            $this->sessionCookie = Cache::get($sessionKey);

            return;
        }

        $response = $this->http->retry(3, 100)
            ->post('Login', [
                'CompanyDB' => $this->config['database'],
                'UserName' => $this->config['username'],
                'Password' => $this->config['password'],
            ]);

        if ($response->failed()) {
            throw new Exception('SAP B1 Login Failed: '.$response->body());
        }

        $this->sessionCookie = $response->header('Set-Cookie');
        Cache::put($sessionKey, $this->sessionCookie, $this->config['cache_ttl']);
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
}
