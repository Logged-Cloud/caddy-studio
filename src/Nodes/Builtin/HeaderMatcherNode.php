<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/**
 * Narrows a route to requests carrying a header value · the websocket-upgrade
 * matcher in the carla subroute is a Connection: *Upgrade* header matcher.
 */
class HeaderMatcherNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'matcher.header';
    }

    public static function label(): string
    {
        return 'Header matcher';
    }

    public static function icon(): string
    {
        return '🏷️';
    }

    public static function group(): string
    {
        return 'matcher';
    }

    public static function description(): string
    {
        return 'Match a request header value.';
    }

    public static function outputs(): array
    {
        return ['matcher' => ['label' => 'Matcher', 'type' => 'caddy.matcher']];
    }

    public static function settings(): array
    {
        return [
            'name'  => ['kind' => 'text', 'label' => 'Header name', 'default' => 'Connection'],
            'value' => ['kind' => 'text', 'label' => 'Value (use * to wildcard)', 'default' => '*Upgrade*'],
        ];
    }
}
