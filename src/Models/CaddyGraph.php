<?php

namespace LoggedCloud\CaddyStudio\Models;

use Illuminate\Database\Eloquent\Model;
use LoggedCloud\CaddyStudio\Support\CaddyCompiler;

class CaddyGraph extends Model
{
    protected $guarded = [];

    protected $casts = [
        'nodes'          => 'array',
        'edges'          => 'array',
        'applied_config' => 'array',
        'is_active'      => 'boolean',
        'applied_at'     => 'datetime',
    ];

    public function getTable(): string
    {
        return config('caddy-studio.table_prefix', 'caddy_studio_').'graphs';
    }

    public function getConnectionName(): ?string
    {
        return config('caddy-studio.connection') ?? parent::getConnectionName();
    }

    /**
     * Compile this graph to the Caddy routes JSON the admin API expects.
     *
     * @return array<int, array<string, mixed>>
     */
    public function compileRoutes(): array
    {
        return CaddyCompiler::compile((array) $this->nodes, (array) $this->edges);
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }
}
