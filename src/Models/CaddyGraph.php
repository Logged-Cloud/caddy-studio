<?php

namespace LoggedCloud\CaddyStudio\Models;

use Illuminate\Database\Eloquent\Model;
use LoggedCloud\CaddyStudio\Support\CaddyClient;
use LoggedCloud\CaddyStudio\Support\CaddyCompiler;
use LoggedCloud\CaddyStudio\Support\DriftDetector;

class CaddyGraph extends Model
{
    protected $guarded = [];

    protected $casts = [
        'nodes'            => 'array',
        'edges'            => 'array',
        'applied_config'   => 'array',
        'is_active'        => 'boolean',
        'applied_at'       => 'datetime',
        'drift'            => 'array',
        'drift_checked_at' => 'datetime',
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

    /**
     * Push this graph's compiled routes to the live Caddy server and record
     * what was applied.
     */
    public function apply(CaddyClient $client): void
    {
        $routes = $this->compileRoutes();
        $client->applyRoutes($routes);

        $this->forceFill([
            'applied_config' => $routes,
            'applied_at'     => now(),
        ])->save();
    }

    /**
     * Compare this graph against the live server and store the drift report.
     *
     * @return array<string, mixed>
     */
    public function checkDrift(CaddyClient $client): array
    {
        $report = DriftDetector::compare($this->compileRoutes(), $client->routes());

        $this->forceFill([
            'drift'            => $report,
            'drift_checked_at' => now(),
        ])->save();

        return $report;
    }
}
