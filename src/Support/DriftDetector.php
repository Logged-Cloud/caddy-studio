<?php

namespace LoggedCloud\CaddyStudio\Support;

/**
 * Compares the routes a graph compiles to against the routes actually live on
 * the Caddy server. Never changes anything · it only reports, so the UI can
 * surface drift and the operator can decide whether to Apply.
 *
 * Routes are keyed by their host match (the natural identity of a route in
 * this stack). A route with no host match is keyed "(no host)".
 */
class DriftDetector
{
    /**
     * @param  array<int, array<string, mixed>>  $graphRoutes  what the graph compiles to
     * @param  array<int, array<string, mixed>>  $liveRoutes   what Caddy currently serves
     * @return array{
     *   in_sync: bool,
     *   graph_count: int,
     *   live_count: int,
     *   only_in_graph: array<int, string>,
     *   only_in_live: array<int, string>,
     *   changed: array<int, string>
     * }
     */
    public static function compare(array $graphRoutes, array $liveRoutes): array
    {
        $graph = self::keyByHost($graphRoutes);
        $live  = self::keyByHost($liveRoutes);

        $onlyInGraph = array_values(array_diff(array_keys($graph), array_keys($live)));
        $onlyInLive  = array_values(array_diff(array_keys($live), array_keys($graph)));

        $changed = [];
        foreach ($graph as $host => $route) {
            if (! isset($live[$host])) {
                continue;
            }
            if (self::canonical($route) !== self::canonical($live[$host])) {
                $changed[] = $host;
            }
        }

        return [
            'in_sync'       => $onlyInGraph === [] && $onlyInLive === [] && $changed === [],
            'graph_count'   => count($graphRoutes),
            'live_count'    => count($liveRoutes),
            'only_in_graph' => $onlyInGraph,
            'only_in_live'  => $onlyInLive,
            'changed'       => $changed,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $routes
     * @return array<string, array<string, mixed>>
     */
    protected static function keyByHost(array $routes): array
    {
        $out = [];
        foreach ($routes as $route) {
            $hosts = $route['match'][0]['host'] ?? null;
            $key = is_array($hosts) && $hosts !== [] ? implode(',', $hosts) : '(no host)';
            $out[$key] = $route;
        }

        return $out;
    }

    protected static function canonical(array $route): string
    {
        $sort = function (&$value) use (&$sort) {
            if (is_array($value)) {
                foreach ($value as &$v) {
                    $sort($v);
                }
                unset($v);
                if (array_keys($value) !== range(0, count($value) - 1)) {
                    ksort($value);
                }
            }
        };
        $sort($route);

        return json_encode($route);
    }
}
