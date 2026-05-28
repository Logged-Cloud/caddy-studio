<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/**
 * A static redirect handler · sends matched requests to another URL with a
 * chosen status. Wire it to a Route's Target in place of an upstream.
 */
class RedirectNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'redirect';
    }

    public static function label(): string
    {
        return 'Redirect';
    }

    public static function icon(): string
    {
        return '↪️';
    }

    public static function group(): string
    {
        return 'handler';
    }

    public static function description(): string
    {
        return 'Redirect matched requests to another URL.';
    }

    public static function outputs(): array
    {
        return [
            'target' => ['label' => 'Redirect', 'type' => 'caddy.target'],
        ];
    }

    public static function settings(): array
    {
        return [
            'to'     => ['kind' => 'text', 'label' => 'Destination URL', 'default' => 'https://{http.request.host}{http.request.uri}'],
            'status' => [
                'kind'    => 'select',
                'label'   => 'Status',
                'default' => '308',
                'options' => ['301', '302', '307', '308'],
            ],
        ];
    }
}
