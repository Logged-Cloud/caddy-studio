<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/**
 * Groups several inner Routes under one handler · the way the carla photo site
 * splits a websocket-upgrade request to the Vite dev server from normal HTTP
 * to nginx. Wire Route nodes' "As inner route" output into this, then wire
 * this to an outer Route's Target.
 */
class SubrouteNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'subroute';
    }

    public static function label(): string
    {
        return 'Subroute';
    }

    public static function icon(): string
    {
        return '🔀';
    }

    public static function group(): string
    {
        return 'handler';
    }

    public static function description(): string
    {
        return 'Branch to different handlers by matcher within one host.';
    }

    public static function inputs(): array
    {
        return [
            'routes' => ['label' => 'Inner routes', 'type' => 'caddy.route', 'many' => true],
        ];
    }

    public static function outputs(): array
    {
        return ['target' => ['label' => 'Handler', 'type' => 'caddy.target']];
    }
}
