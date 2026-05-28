<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/** Narrows a route to requests with a matching query parameter. */
class QueryMatcherNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'matcher.query';
    }

    public static function label(): string
    {
        return 'Query matcher';
    }

    public static function icon(): string
    {
        return '❓';
    }

    public static function group(): string
    {
        return 'matcher';
    }

    public static function description(): string
    {
        return 'Match a query-string parameter.';
    }

    public static function outputs(): array
    {
        return ['matcher' => ['label' => 'Matcher', 'type' => 'caddy.matcher']];
    }

    public static function settings(): array
    {
        return [
            'key'   => ['kind' => 'text', 'label' => 'Param', 'default' => ''],
            'value' => ['kind' => 'text', 'label' => 'Value', 'default' => ''],
        ];
    }
}
