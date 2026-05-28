<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/**
 * Pools several Upstreams behind one reverse_proxy with a selectable
 * load-balancing policy. Wire many Upstreams into it, wire its output to a
 * Route · one wire in, many backends out.
 */
class LoadBalancerNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'lb';
    }

    public static function label(): string
    {
        return 'Load Balancer';
    }

    public static function icon(): string
    {
        return '⚖️';
    }

    public static function group(): string
    {
        return 'handler';
    }

    public static function description(): string
    {
        return 'Spread traffic across many upstreams with a balancing policy.';
    }

    public static function inputs(): array
    {
        return [
            'upstreams' => ['label' => 'Upstreams', 'type' => 'caddy.target', 'many' => true],
        ];
    }

    public static function outputs(): array
    {
        return [
            'target' => ['label' => 'Balanced', 'type' => 'caddy.target'],
        ];
    }

    public static function settings(): array
    {
        return [
            'policy' => [
                'kind'    => 'select',
                'label'   => 'Policy',
                'default' => 'round_robin',
                'options' => array_keys(config('caddy-studio.lb_policies', ['round_robin' => 'Round robin'])),
            ],
        ];
    }
}
