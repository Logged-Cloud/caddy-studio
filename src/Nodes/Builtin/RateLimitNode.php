<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/**
 * Throttles requests. A middleware · wire Next to the handler it fronts.
 * Requires the caddy-ratelimit plugin on the server.
 */
class RateLimitNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'rate_limit';
    }

    public static function label(): string
    {
        return 'Rate limit';
    }

    public static function icon(): string
    {
        return '🚦';
    }

    public static function group(): string
    {
        return 'handler';
    }

    public static function description(): string
    {
        return 'Throttle requests (needs the rate_limit plugin).';
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
            'rate' => ['kind' => 'text', 'label' => 'Rate (e.g. 100r/m)', 'default' => '100r/m'],
        ];
    }
}
