<?php

namespace LoggedCloud\CaddyStudio\Support;

/**
 * Compiles a node graph into the Caddy admin-API routes JSON.
 *
 * Graph shape (the same shape the editor saves):
 *   $nodes = [
 *     ['id' => 's',  'type' => 'server',     'settings' => ['listen' => ':443']],
 *     ['id' => 'r',  'type' => 'route.host', 'settings' => ['hosts' => 'studio.logged.cloud', 'terminal' => true]],
 *     ['id' => 'u',  'type' => 'upstream',   'settings' => ['dial' => '10.0.0.200:8107']],
 *   ];
 *   $edges = [
 *     ['from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target'],
 *   ];
 *
 * Each `route.host` node becomes one top-level Caddy route. Its handler is
 * resolved by following the wire into its `target` input:
 *   - an `upstream` node          → reverse_proxy with one backend
 *   - an `lb` (load balancer) node → reverse_proxy with a policy + many backends
 *   - a `redirect` node            → static_response
 */
class CaddyCompiler
{
    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array<string, mixed>>  Caddy routes
     */
    public static function compile(array $nodes, array $edges): array
    {
        $byId = [];
        foreach ($nodes as $n) {
            if (! empty($n['id'])) {
                $byId[$n['id']] = $n;
            }
        }

        $routes = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) !== 'route.host') {
                continue;
            }
            $route = self::compileRoute($node, $byId, $edges);
            if ($route !== null) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /**
     * Full server object for apps.http.servers.<server> · listen + routes.
     *
     * @return array<string, mixed>
     */
    public static function compileServer(array $nodes, array $edges): array
    {
        $listen = ':443';
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'server') {
                $listen = trim((string) ($node['settings']['listen'] ?? ':443')) ?: ':443';
                break;
            }
        }

        return [
            'listen' => [$listen],
            'routes' => self::compile($nodes, $edges),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $byId
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<string, mixed>|null
     */
    protected static function compileRoute(array $node, array $byId, array $edges): ?array
    {
        $settings = (array) ($node['settings'] ?? []);

        $hosts = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($settings['hosts'] ?? ''))
        )));

        $handle = self::resolveHandler($node['id'], $byId, $edges);
        if ($handle === null) {
            return null;
        }

        $route = [];
        if ($hosts !== []) {
            $route['match'] = [['host' => $hosts]];
        }
        $route['handle'] = [$handle];

        if (($settings['terminal'] ?? true)) {
            $route['terminal'] = true;
        }

        return $route;
    }

    /**
     * Follow the wire into a route's `target` input and build the handler.
     *
     * @return array<string, mixed>|null
     */
    protected static function resolveHandler(string $routeId, array $byId, array $edges): ?array
    {
        $source = self::sourceNode($routeId, 'target', $byId, $edges);
        if ($source === null) {
            return null;
        }

        return match ($source['type'] ?? null) {
            'upstream' => self::reverseProxy(
                [self::upstreamDial($source)],
                self::needsTls([$source]) ? $source : null,
            ),
            'lb' => self::loadBalancedProxy($source, $byId, $edges),
            'redirect' => self::redirect($source),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function loadBalancedProxy(array $lb, array $byId, array $edges): array
    {
        $upstreamNodes = self::sourceNodes($lb['id'], 'upstreams', $byId, $edges);
        $dials = array_map(fn ($u) => self::upstreamDial($u), $upstreamNodes);

        $policy = (string) ($lb['settings']['policy'] ?? 'round_robin');

        $proxy = self::reverseProxy(
            $dials,
            self::needsTls($upstreamNodes) ? $upstreamNodes[0] : null,
        );
        $proxy['load_balancing'] = ['selection_policy' => ['policy' => $policy]];

        return $proxy;
    }

    /**
     * @param  array<int, string>  $dials
     * @return array<string, mixed>
     */
    protected static function reverseProxy(array $dials, ?array $tlsSource): array
    {
        $proxy = ['handler' => 'reverse_proxy'];

        if ($tlsSource !== null) {
            $proxy['transport'] = [
                'protocol' => 'http',
                'tls'      => ['insecure_skip_verify' => true],
            ];
        }

        $proxy['upstreams'] = array_values(array_map(
            fn ($dial) => ['dial' => $dial],
            array_filter($dials, fn ($d) => $d !== '')
        ));

        return $proxy;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function redirect(array $node): array
    {
        $to = (string) ($node['settings']['to'] ?? '');
        $status = (int) ($node['settings']['status'] ?? 308);

        return [
            'handler'     => 'static_response',
            'status_code' => $status,
            'headers'     => ['Location' => [$to]],
        ];
    }

    protected static function upstreamDial(array $upstream): string
    {
        return trim((string) ($upstream['settings']['dial'] ?? ''));
    }

    /** @param array<int, array<string,mixed>> $upstreams */
    protected static function needsTls(array $upstreams): bool
    {
        foreach ($upstreams as $u) {
            if (! empty($u['settings']['skip_tls_verify'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The single node wired into ($toNode, $toSocket), or null.
     *
     * @return array<string, mixed>|null
     */
    protected static function sourceNode(string $toNode, string $toSocket, array $byId, array $edges): ?array
    {
        foreach ($edges as $e) {
            if (($e['to_node'] ?? null) === $toNode && ($e['to_socket'] ?? null) === $toSocket) {
                return $byId[$e['from_node'] ?? ''] ?? null;
            }
        }

        return null;
    }

    /**
     * Every node wired into ($toNode, $toSocket), preserving node declaration
     * order for stable output.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function sourceNodes(string $toNode, string $toSocket, array $byId, array $edges): array
    {
        $sourceIds = [];
        foreach ($edges as $e) {
            if (($e['to_node'] ?? null) === $toNode && ($e['to_socket'] ?? null) === $toSocket) {
                $sourceIds[] = $e['from_node'] ?? null;
            }
        }

        $out = [];
        foreach ($byId as $id => $node) {
            if (in_array($id, $sourceIds, true)) {
                $out[] = $node;
            }
        }

        return $out;
    }
}
