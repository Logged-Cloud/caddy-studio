<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/**
 * A single backend dial target · `host:port`. Wire it straight to a Route for
 * a one-upstream reverse_proxy, or into a Load Balancer to pool it with others.
 */
class UpstreamNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'upstream';
    }

    public static function label(): string
    {
        return 'Upstream';
    }

    public static function icon(): string
    {
        return '🎯';
    }

    public static function group(): string
    {
        return 'upstream';
    }

    public static function description(): string
    {
        return 'A backend server to proxy to, e.g. 10.0.0.200:8107.';
    }

    public static function outputs(): array
    {
        return [
            'upstream' => ['label' => 'Upstream', 'type' => 'caddy.target'],
        ];
    }

    public static function settings(): array
    {
        return [
            'dial'            => ['kind' => 'text', 'label' => 'Dial (host:port)', 'default' => ''],
            'skip_tls_verify' => ['kind' => 'bool', 'label' => 'Skip TLS verify (https backend)', 'default' => false],
        ];
    }
}
