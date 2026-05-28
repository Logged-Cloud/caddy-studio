<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/** Compresses responses (gzip / zstd). A middleware · wire Next to the handler it precedes. */
class EncodeNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'encode';
    }

    public static function label(): string
    {
        return 'Compression';
    }

    public static function icon(): string
    {
        return '🗜️';
    }

    public static function group(): string
    {
        return 'handler';
    }

    public static function description(): string
    {
        return 'Gzip / zstd response compression.';
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
            'gzip' => ['kind' => 'bool', 'label' => 'gzip', 'default' => true],
            'zstd' => ['kind' => 'bool', 'label' => 'zstd', 'default' => true],
        ];
    }
}
