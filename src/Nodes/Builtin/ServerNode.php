<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/**
 * Global server options · the listen address every compiled route is served
 * on. Optional: when absent the compiler defaults to ":443". One per graph.
 */
class ServerNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'server';
    }

    public static function label(): string
    {
        return 'Server';
    }

    public static function icon(): string
    {
        return '🖥️';
    }

    public static function group(): string
    {
        return 'server';
    }

    public static function description(): string
    {
        return 'The listen address all routes are served on.';
    }

    public static function settings(): array
    {
        return [
            'listen' => ['kind' => 'text', 'label' => 'Listen', 'default' => ':443'],
        ];
    }
}
