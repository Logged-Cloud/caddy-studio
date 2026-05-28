<?php

namespace LoggedCloud\CaddyStudio\Console;

use Illuminate\Console\Command;
use LoggedCloud\CaddyStudio\Models\CaddyGraph;
use LoggedCloud\CaddyStudio\Support\CaddyClient;

class ApplyGraphCommand extends Command
{
    protected $signature = 'caddy-studio:apply {graph? : Graph id (defaults to the active graph)}';

    protected $description = 'Compile a graph and push its routes to the live Caddy server';

    public function handle(CaddyClient $client): int
    {
        $graph = $this->argument('graph')
            ? CaddyGraph::find($this->argument('graph'))
            : CaddyGraph::active();

        if (! $graph) {
            $this->error('No graph to apply (none given and no active graph).');

            return self::FAILURE;
        }

        if (! $client->reachable()) {
            $this->error('Caddy admin API is not reachable at '.config('caddy-studio.admin.url').'.');

            return self::FAILURE;
        }

        $graph->apply($client);
        $this->info("Applied graph #{$graph->id} ({$graph->name}): ".count($graph->compileRoutes()).' route(s).');

        return self::SUCCESS;
    }
}
