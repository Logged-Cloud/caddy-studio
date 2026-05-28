<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/** Returns a fixed status + body. Handy for health checks and maintenance pages. */
class StaticResponseNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'static_response';
    }

    public static function label(): string
    {
        return 'Static response';
    }

    public static function icon(): string
    {
        return '📄';
    }

    public static function group(): string
    {
        return 'handler';
    }

    public static function description(): string
    {
        return 'Return a fixed status code + body.';
    }

    public static function outputs(): array
    {
        return ['target' => ['label' => 'Handler', 'type' => 'caddy.target']];
    }

    public static function settings(): array
    {
        return [
            'status' => ['kind' => 'number', 'label' => 'Status code', 'default' => 200],
            'body'   => ['kind' => 'textarea', 'label' => 'Body', 'default' => ''],
        ];
    }
}
