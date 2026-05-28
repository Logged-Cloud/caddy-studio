<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/**
 * A top-level Caddy route matched by host. Wire its Target input to an
 * Upstream (direct reverse_proxy), a Load Balancer (reverse_proxy with a
 * policy + many upstreams), or a Redirect.
 */
class RouteHostNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'route.host';
    }

    public static function label(): string
    {
        return 'Route';
    }

    public static function icon(): string
    {
        return '🌐';
    }

    public static function group(): string
    {
        return 'route';
    }

    public static function description(): string
    {
        return 'Match one or more hostnames and proxy them somewhere.';
    }

    public static function inputs(): array
    {
        return [
            'target'   => ['label' => 'Target', 'type' => 'caddy.target'],
            'matchers' => ['label' => 'Matchers', 'type' => 'caddy.matcher', 'many' => true],
        ];
    }

    public static function outputs(): array
    {
        // Wire this into a Subroute to make it an inner route instead of a
        // top-level one.
        return [
            'route' => ['label' => 'As inner route', 'type' => 'caddy.route'],
        ];
    }

    public static function settings(): array
    {
        return [
            'hosts'    => ['kind' => 'text', 'label' => 'Hosts (comma separated)', 'default' => ''],
            'terminal' => ['kind' => 'bool', 'label' => 'Terminal', 'default' => true],
        ];
    }
}
