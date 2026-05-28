<?php

namespace LoggedCloud\CaddyStudio\Console;

use Illuminate\Console\Command;
use LoggedCloud\CaddyStudio\Models\CaddyGraph;
use LoggedCloud\CaddyStudio\Support\CaddyClient;

class CheckDriftCommand extends Command
{
    protected $signature = 'caddy-studio:check-drift {graph? : Graph id (defaults to the active graph)}';

    protected $description = 'Compare the active graph against the live Caddy config and report drift';

    public function handle(CaddyClient $client): int
    {
        $graph = $this->argument('graph')
            ? CaddyGraph::find($this->argument('graph'))
            : CaddyGraph::active();

        if (! $graph) {
            $this->error('No graph to check (none given and no active graph).');

            return self::FAILURE;
        }

        if (! $client->reachable()) {
            $this->error('Caddy admin API is not reachable at '.config('caddy-studio.admin.url').'.');

            return self::FAILURE;
        }

        $report = $graph->checkDrift($client);

        if ($report['in_sync']) {
            $this->info("Graph #{$graph->id} ({$graph->name}) is in sync with Caddy ({$report['live_count']} route(s)).");

            return self::SUCCESS;
        }

        $this->warn("Graph #{$graph->id} ({$graph->name}) has drifted from Caddy:");
        if ($report['only_in_graph']) {
            $this->line('  Not yet applied (in graph, not live): '.implode(', ', $report['only_in_graph']));
        }
        if ($report['only_in_live']) {
            $this->line('  Unmanaged (live, not in graph):        '.implode(', ', $report['only_in_live']));
        }
        if ($report['changed']) {
            $this->line('  Changed (differ graph vs live):        '.implode(', ', $report['changed']));
        }

        // Drift is a reportable condition, not a command failure · the
        // scheduler should not treat "drifted" as an error exit.
        return self::SUCCESS;
    }
}
