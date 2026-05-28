<?php

namespace LoggedCloud\CaddyStudio\Nodes;

/**
 * Descriptor for a Caddy node · unlike page-studio's NodeType (which computes
 * a value via evaluate()), a Caddy node is structural: it declares the sockets
 * + settings the canvas renders, and the CaddyCompiler walks the wired graph
 * to assemble the final Caddy admin JSON. Built-ins live in Nodes\Builtin and
 * the compiler switches on their key(); apps can register their own.
 */
abstract class CaddyNodeType
{
    /** Unique identifier · convention `<group>.<snake_name>` or a bare verb. */
    abstract public static function key(): string;

    /** Label shown in palettes + node headers. */
    abstract public static function label(): string;

    /** Emoji / glyph rendered in the node header. */
    public static function icon(): string
    {
        return '◆';
    }

    /**
     * Palette section · 'server' | 'route' | 'upstream' | 'handler'
     * | 'matcher' | 'tls' | 'note'.
     */
    public static function group(): string
    {
        return 'handler';
    }

    /** One-line description shown in the palette + node tooltip. */
    public static function description(): string
    {
        return '';
    }

    /** @return array<string, array{label?: string, type?: string, many?: bool}> */
    public static function inputs(): array
    {
        return [];
    }

    /** @return array<string, array{label?: string, type?: string}> */
    public static function outputs(): array
    {
        return [];
    }

    /**
     * Per-node setting fields rendered into the node body. Same shape as
     * page-studio:
     *   ['kind' => 'text|number|select|bool|textarea',
     *    'label' => '...', 'default' => ..., 'options' => [...]]
     *
     * @return array<string, array<string, mixed>>
     */
    public static function settings(): array
    {
        return [];
    }

    /**
     * Config-shape the palette renderer + canvas read.
     */
    public static function toLibraryEntry(): array
    {
        return [
            'group'       => static::group(),
            'label'       => static::label(),
            'icon'        => static::icon(),
            'description' => static::description(),
            'inputs'      => static::inputs(),
            'outputs'     => static::outputs(),
            'settings'    => static::settings(),
            'class'       => static::class,
        ];
    }
}
