<?php

namespace LoggedCloud\CaddyStudio\Support;

/**
 * Compiles a node graph into the Caddy admin-API routes JSON (and TLS
 * automation policies).
 *
 * Each `route.host` node becomes one Caddy route unless it is wired into a
 * Subroute (then it is an inner route of that subroute). A route's `handle`
 * is the chain produced by walking its `target` input:
 *
 *   route.target → encode → headers → reverse_proxy(upstream)
 *   compiles to   handle: [encode, headers, reverse_proxy]
 *
 * Terminal handlers (upstream, lb, redirect, file_server, static_response,
 * fastcgi, subroute) end a chain. Middleware handlers (encode, headers,
 * rewrite, basic_auth, rate_limit) have a `next` input and prepend themselves
 * to whatever that resolves to.
 *
 * Matcher nodes (path/method/header/query) wired into a route's `matchers`
 * input merge into the route's match object alongside its host list.
 */
class CaddyCompiler
{
    protected const TERMINALS = ['upstream', 'lb', 'redirect', 'file_server', 'static_response', 'fastcgi', 'subroute'];
    protected const MIDDLEWARE = ['encode', 'headers', 'rewrite', 'basic_auth', 'rate_limit'];

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array<string, mixed>>
     */
    public static function compile(array $nodes, array $edges): array
    {
        $byId = self::index($nodes);
        $inner = self::innerRouteIds($byId, $edges);

        $routes = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) !== 'route.host') {
                continue;
            }
            if (in_array($node['id'] ?? null, $inner, true)) {
                continue; // belongs to a subroute, not the top level
            }
            $route = self::compileRoute($node, $byId, $edges);
            if ($route !== null) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /**
     * Full server object for apps.http.servers.<server>.
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
     * TLS automation policies from `tls.*` nodes. Empty when none · Caddy then
     * falls back to its default HTTP-01 issuer for every host.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function compileTls(array $nodes, array $edges): array
    {
        $policies = [];
        foreach ($nodes as $node) {
            $policy = match ($node['type'] ?? null) {
                'tls.duckdns' => self::duckdnsPolicy($node),
                default       => null,
            };
            if ($policy !== null) {
                $policies[] = $policy;
            }
        }

        return $policies;
    }

    // ---- routes -----------------------------------------------------------

    protected static function compileRoute(array $node, array $byId, array $edges): ?array
    {
        $handle = self::resolveChain($node['id'], 'target', $byId, $edges);
        if ($handle === []) {
            return null;
        }

        $route = [];
        $match = self::resolveMatch($node, $byId, $edges);
        if ($match !== []) {
            $route['match'] = [$match];
        }
        $route['handle'] = $handle;

        if ((($node['settings'] ?? [])['terminal'] ?? true)) {
            $route['terminal'] = true;
        }

        return $route;
    }

    /**
     * The match object for a route · host list (from settings) merged with
     * every wired matcher node.
     *
     * @return array<string, mixed>
     */
    protected static function resolveMatch(array $route, array $byId, array $edges): array
    {
        $match = [];

        $hosts = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) (($route['settings'] ?? [])['hosts'] ?? ''))
        )));
        if ($hosts !== []) {
            $match['host'] = $hosts;
        }

        foreach (self::sourceNodes($route['id'], 'matchers', $byId, $edges) as $m) {
            $match = array_merge($match, self::matcherFragment($m));
        }

        return $match;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function matcherFragment(array $node): array
    {
        $s = (array) ($node['settings'] ?? []);

        return match ($node['type'] ?? null) {
            'matcher.path'   => ['path' => self::splitList($s['paths'] ?? '')],
            'matcher.method' => ['method' => array_map('strtoupper', self::splitList($s['methods'] ?? ''))],
            'matcher.header' => ['header' => [(string) ($s['name'] ?? '') => [(string) ($s['value'] ?? '')]]],
            'matcher.query'  => ['query' => [(string) ($s['key'] ?? '') => [(string) ($s['value'] ?? '')]]],
            default          => [],
        };
    }

    // ---- handler chain ----------------------------------------------------

    /**
     * Walk from ($toNode, $toSocket) and build the ordered handle array.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function resolveChain(string $toNode, string $toSocket, array $byId, array $edges): array
    {
        $source = self::sourceNode($toNode, $toSocket, $byId, $edges);
        if ($source === null) {
            return [];
        }

        $type = $source['type'] ?? null;

        if (in_array($type, self::MIDDLEWARE, true)) {
            return array_merge(
                [self::middlewareHandler($source)],
                self::resolveChain($source['id'], 'next', $byId, $edges),
            );
        }

        $terminal = self::terminalHandler($source, $byId, $edges);

        return $terminal === null ? [] : [$terminal];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function terminalHandler(array $node, array $byId, array $edges): ?array
    {
        return match ($node['type'] ?? null) {
            'upstream' => self::reverseProxy(
                [self::dial($node)],
                self::needsTls([$node]) ? $node : null,
            ),
            'lb'              => self::loadBalancedProxy($node, $byId, $edges),
            'redirect'        => self::redirect($node),
            'file_server'     => self::fileServer($node),
            'static_response' => self::staticResponse($node),
            'fastcgi'         => self::fastcgi($node),
            'subroute'        => self::subroute($node, $byId, $edges),
            default           => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function middlewareHandler(array $node): array
    {
        $s = (array) ($node['settings'] ?? []);

        return match ($node['type'] ?? null) {
            'encode' => [
                'handler'   => 'encode',
                'encodings' => self::encodings($s),
            ],
            'headers' => [
                'handler'  => 'headers',
                'response' => ['set' => [(string) ($s['name'] ?? '') => [(string) ($s['value'] ?? '')]]],
            ],
            'rewrite' => [
                'handler' => 'rewrite',
                'uri'     => (string) ($s['uri'] ?? ''),
            ],
            'basic_auth' => [
                'handler'   => 'authentication',
                'providers' => ['http_basic' => ['accounts' => [[
                    'username' => (string) ($s['username'] ?? ''),
                    'password' => (string) ($s['password_hash'] ?? ''),
                ]]]],
            ],
            'rate_limit' => [
                'handler' => 'rate_limit',
                'rate'    => (string) ($s['rate'] ?? '100r/m'),
            ],
            default => ['handler' => 'static_response', 'status_code' => 500],
        };
    }

    // ---- terminal handlers ------------------------------------------------

    protected static function loadBalancedProxy(array $lb, array $byId, array $edges): array
    {
        $upstreamNodes = self::sourceNodes($lb['id'], 'upstreams', $byId, $edges);
        $dials = array_map(fn ($u) => self::dial($u), $upstreamNodes);

        $proxy = self::reverseProxy(
            $dials,
            self::needsTls($upstreamNodes) ? ($upstreamNodes[0] ?? null) : null,
        );
        $proxy['load_balancing'] = ['selection_policy' => ['policy' => (string) ($lb['settings']['policy'] ?? 'round_robin')]];

        $s = (array) ($lb['settings'] ?? []);
        if (! empty($s['health_path'])) {
            $proxy['health_checks'] = ['active' => array_filter([
                'path'     => (string) $s['health_path'],
                'interval' => (string) ($s['health_interval'] ?? '30s'),
            ])];
        }

        return $proxy;
    }

    /**
     * @param  array<int, string>  $dials
     */
    protected static function reverseProxy(array $dials, ?array $tlsSource): array
    {
        $proxy = ['handler' => 'reverse_proxy'];

        if ($tlsSource !== null) {
            $proxy['transport'] = ['protocol' => 'http', 'tls' => ['insecure_skip_verify' => true]];
        }

        $proxy['upstreams'] = array_values(array_map(
            fn ($dial) => ['dial' => $dial],
            array_filter($dials, fn ($d) => $d !== '')
        ));

        return $proxy;
    }

    protected static function redirect(array $node): array
    {
        return [
            'handler'     => 'static_response',
            'status_code' => (int) ($node['settings']['status'] ?? 308),
            'headers'     => ['Location' => [(string) ($node['settings']['to'] ?? '')]],
        ];
    }

    protected static function fileServer(array $node): array
    {
        $s = (array) ($node['settings'] ?? []);
        $h = ['handler' => 'file_server', 'root' => (string) ($s['root'] ?? '')];
        if (! empty($s['browse'])) {
            $h['browse'] = new \stdClass();
        }

        return $h;
    }

    protected static function staticResponse(array $node): array
    {
        $s = (array) ($node['settings'] ?? []);
        $h = ['handler' => 'static_response', 'status_code' => (int) ($s['status'] ?? 200)];
        if (($s['body'] ?? '') !== '') {
            $h['body'] = (string) $s['body'];
        }

        return $h;
    }

    protected static function fastcgi(array $node): array
    {
        $s = (array) ($node['settings'] ?? []);

        return [
            'handler'   => 'reverse_proxy',
            'transport' => ['protocol' => 'fastcgi', 'split_path' => ['.php']],
            'upstreams' => [['dial' => trim((string) ($s['dial'] ?? ''))]],
        ];
    }

    protected static function subroute(array $node, array $byId, array $edges): array
    {
        $routes = [];
        foreach (self::sourceNodes($node['id'], 'routes', $byId, $edges) as $inner) {
            if (($inner['type'] ?? null) !== 'route.host') {
                continue;
            }
            $route = self::compileRoute($inner, $byId, $edges);
            if ($route !== null) {
                // Inner routes of a subroute are not themselves terminal by
                // default · let the order + matchers decide the fallthrough.
                unset($route['terminal']);
                $routes[] = $route;
            }
        }

        return ['handler' => 'subroute', 'routes' => $routes];
    }

    // ---- tls --------------------------------------------------------------

    protected static function duckdnsPolicy(array $node): array
    {
        $s = (array) ($node['settings'] ?? []);

        return [
            'subjects' => self::splitList($s['subjects'] ?? ''),
            'issuers'  => [[
                'module'     => 'acme',
                'challenges' => ['dns' => ['provider' => [
                    'name'      => 'duckdns',
                    'api_token' => (string) ($s['api_token'] ?? ''),
                ]]],
            ]],
        ];
    }

    // ---- helpers ----------------------------------------------------------

    /** @return array<string, mixed> */
    protected static function encodings(array $s): array
    {
        $out = [];
        foreach (['gzip', 'zstd'] as $enc) {
            if (! array_key_exists($enc, $s) || $s[$enc]) {
                $out[$enc] = new \stdClass();
            }
        }

        return $out === [] ? ['gzip' => new \stdClass()] : $out;
    }

    protected static function dial(array $upstream): string
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

    /** @return array<int, string> */
    protected static function splitList(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    /** @return array<string, array<string, mixed>> */
    protected static function index(array $nodes): array
    {
        $byId = [];
        foreach ($nodes as $n) {
            if (! empty($n['id'])) {
                $byId[$n['id']] = $n;
            }
        }

        return $byId;
    }

    /**
     * Ids of route.host nodes that are wired into a subroute (so they are inner
     * routes, not top-level ones).
     *
     * @return array<int, string>
     */
    protected static function innerRouteIds(array $byId, array $edges): array
    {
        $ids = [];
        foreach ($edges as $e) {
            $to = $byId[$e['to_node'] ?? ''] ?? null;
            if (($to['type'] ?? null) === 'subroute' && ($e['to_socket'] ?? null) === 'routes') {
                $ids[] = $e['from_node'] ?? null;
            }
        }

        return array_values(array_filter($ids));
    }

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
