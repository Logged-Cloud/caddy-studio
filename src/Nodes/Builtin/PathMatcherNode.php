<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/** Narrows a route to matching request paths. Wire into a Route's Matchers. */
class PathMatcherNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'matcher.path';
    }

    public static function label(): string
    {
        return 'Path matcher';
    }

    public static function icon(): string
    {
        return '🛣️';
    }

    public static function group(): string
    {
        return 'matcher';
    }

    public static function description(): string
    {
        return 'Match request paths, e.g. /api/*';
    }

    public static function outputs(): array
    {
        return ['matcher' => ['label' => 'Matcher', 'type' => 'caddy.matcher']];
    }

    public static function settings(): array
    {
        return [
            'paths' => ['kind' => 'text', 'label' => 'Paths (comma separated)', 'default' => '/*'],
        ];
    }
}
