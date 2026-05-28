<?php

namespace LoggedCloud\CaddyStudio\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Thin wrapper over the Caddy admin REST API. Reads + writes the routes of one
 * HTTP server (default "srv0"). Everything Apply / drift detection does goes
 * through here.
 *
 * Caddy admin write semantics this relies on:
 *   PATCH <path>  · replace the value already at <path>
 *   POST  <path>  · append to the array at <path>
 */
class CaddyClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $server = 'srv0',
        protected int $timeout = 10,
        protected ?Client $http = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http ??= new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => $this->timeout,
            'headers'  => ['Content-Type' => 'application/json'],
        ]);
    }

    protected function routesPath(): string
    {
        return "/config/apps/http/servers/{$this->server}/routes";
    }

    /**
     * The full live config.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->getJson('/config/');
    }

    /**
     * The live routes of the managed server. Returns [] when the server has
     * no routes yet (Caddy answers `null`).
     *
     * @return array<int, array<string, mixed>>
     */
    public function routes(): array
    {
        return $this->getJson($this->routesPath()) ?: [];
    }

    /**
     * Replace the managed server's entire routes array.
     *
     * @param  array<int, array<string, mixed>>  $routes
     */
    public function applyRoutes(array $routes): void
    {
        $this->send('PATCH', $this->routesPath(), $routes);
    }

    /**
     * Append a single route to the managed server.
     *
     * @param  array<string, mixed>  $route
     */
    public function appendRoute(array $route): void
    {
        $this->send('POST', $this->routesPath(), $route);
    }

    public function reachable(): bool
    {
        try {
            $this->http->request('GET', '/config/');

            return true;
        } catch (GuzzleException) {
            return false;
        }
    }

    protected function getJson(string $path): mixed
    {
        $res = $this->http->request('GET', $path);

        return json_decode((string) $res->getBody(), true);
    }

    protected function send(string $method, string $path, mixed $body): void
    {
        $this->http->request($method, $path, [
            'body' => json_encode($body),
        ]);
    }
}
