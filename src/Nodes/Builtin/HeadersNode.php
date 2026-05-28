<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/** Sets a response header. A middleware · wire Next to the handler it precedes. */
class HeadersNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'headers';
    }

    public static function label(): string
    {
        return 'Set header';
    }

    public static function icon(): string
    {
        return '📋';
    }

    public static function group(): string
    {
        return 'handler';
    }

    public static function description(): string
    {
        return 'Set a response header.';
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
            'name'  => ['kind' => 'text', 'label' => 'Header name', 'default' => 'Strict-Transport-Security'],
            'value' => ['kind' => 'text', 'label' => 'Value', 'default' => 'max-age=31536000'],
        ];
    }
}
