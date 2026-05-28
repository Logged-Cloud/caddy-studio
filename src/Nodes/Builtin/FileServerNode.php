<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/** Serves static files from a directory. Wire to a Route's Target. */
class FileServerNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'file_server';
    }

    public static function label(): string
    {
        return 'File server';
    }

    public static function icon(): string
    {
        return '📁';
    }

    public static function group(): string
    {
        return 'handler';
    }

    public static function description(): string
    {
        return 'Serve static files from a directory.';
    }

    public static function outputs(): array
    {
        return ['target' => ['label' => 'Handler', 'type' => 'caddy.target']];
    }

    public static function settings(): array
    {
        return [
            'root'   => ['kind' => 'text', 'label' => 'Root directory', 'default' => '/var/www/html'],
            'browse' => ['kind' => 'bool', 'label' => 'Directory listing', 'default' => false],
        ];
    }
}
