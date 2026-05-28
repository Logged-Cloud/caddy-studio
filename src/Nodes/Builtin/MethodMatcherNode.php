<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/** Narrows a route to matching HTTP methods. Wire into a Route's Matchers. */
class MethodMatcherNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'matcher.method';
    }

    public static function label(): string
    {
        return 'Method matcher';
    }

    public static function icon(): string
    {
        return '🔧';
    }

    public static function group(): string
    {
        return 'matcher';
    }

    public static function description(): string
    {
        return 'Match HTTP methods, e.g. GET, POST.';
    }

    public static function outputs(): array
    {
        return ['matcher' => ['label' => 'Matcher', 'type' => 'caddy.matcher']];
    }

    public static function settings(): array
    {
        return [
            'methods' => ['kind' => 'text', 'label' => 'Methods (comma separated)', 'default' => 'GET'],
        ];
    }
}
