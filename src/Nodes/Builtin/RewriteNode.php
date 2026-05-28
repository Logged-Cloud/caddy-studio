<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/** Rewrites the request URI internally. A middleware · wire Next to the handler it precedes. */
class RewriteNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'rewrite';
    }

    public static function label(): string
    {
        return 'Rewrite';
    }

    public static function icon(): string
    {
        return '✏️';
    }

    public static function group(): string
    {
        return 'handler';
    }

    public static function description(): string
    {
        return 'Internally rewrite the request URI.';
    }

    public static function inputs(): array
    {
        return ['next' => ['label' => 'Next', 'type' => 'caddy.target']];
    }

    public static function outputs(): array
    {
        return ['target' => ['label' => 'Handler', 'type' => 'caddy.target']];
    }

    public static function settings(): array
    {
        return [
            'uri' => ['kind' => 'text', 'label' => 'New URI', 'default' => '/index.php'],
        ];
    }
}
