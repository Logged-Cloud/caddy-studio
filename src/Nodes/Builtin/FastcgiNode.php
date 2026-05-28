<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/** Proxies to a PHP-FPM (FastCGI) backend. Wire to a Route's Target. */
class FastcgiNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'fastcgi';
    }

    public static function label(): string
    {
        return 'PHP / FastCGI';
    }

    public static function icon(): string
    {
        return '🐘';
    }

    public static function group(): string
    {
        return 'handler';
    }

    public static function description(): string
    {
        return 'Proxy to a PHP-FPM FastCGI backend.';
    }

    public static function outputs(): array
    {
        return ['target' => ['label' => 'Handler', 'type' => 'caddy.target']];
    }

    public static function settings(): array
    {
        return [
            'dial' => ['kind' => 'text', 'label' => 'FastCGI dial (host:9000)', 'default' => ''],
        ];
    }
}
