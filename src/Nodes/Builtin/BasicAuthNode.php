<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/** HTTP basic auth gate. A middleware · wire Next to the handler it protects. */
class BasicAuthNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'basic_auth';
    }

    public static function label(): string
    {
        return 'Basic auth';
    }

    public static function icon(): string
    {
        return '🔒';
    }

    public static function group(): string
    {
        return 'handler';
    }

    public static function description(): string
    {
        return 'Password-protect with HTTP basic auth.';
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
            'username'      => ['kind' => 'text', 'label' => 'Username', 'default' => ''],
            'password_hash' => ['kind' => 'text', 'label' => 'Bcrypt password hash', 'default' => ''],
        ];
    }
}
