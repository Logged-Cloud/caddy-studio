<?php

namespace LoggedCloud\CaddyStudio\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use LoggedCloud\CaddyStudio\Models\CaddyGraph;
use LoggedCloud\CaddyStudio\Nodes\NodeRegistry;
use LoggedCloud\CaddyStudio\Support\CaddyClient;
use LoggedCloud\CaddyStudio\Support\CaddyCompiler;

/**
 * The node-graph editor. Binds to a CaddyGraph, lets you add / wire / configure
 * nodes, preview the compiled Caddy JSON, push it live (Apply), and see drift.
 *
 * Structural edits persist immediately so the graph row is always the source of
 * truth · the same live-persist approach page-studio's PageBuilder uses.
 */
class CaddyBuilder extends Component
{
    public ?int $graphId = null;

    /** @var array<int, array<string, mixed>> */
    public array $nodes = [];

    /** @var array<int, array<string, mixed>> */
    public array $edges = [];

    public ?string $selectedNodeId = null;

    /** Latest drift report (or null until checked). @var array<string,mixed>|null */
    public ?array $drift = null;

    public string $status = '';

    public function mount(?int $graphId = null): void
    {
        $graph = $graphId
            ? CaddyGraph::find($graphId)
            : (CaddyGraph::active() ?? CaddyGraph::first());

        if (! $graph) {
            $graph = CaddyGraph::create([
                'name'      => 'My Caddy config',
                'is_active' => true,
                'nodes'     => [['id' => 'server', 'type' => 'server', 'position' => ['x' => 40, 'y' => 40], 'settings' => ['listen' => ':443']]],
                'edges'     => [],
            ]);
        }

        $this->graphId = $graph->id;
        $this->nodes = (array) $graph->nodes;
        $this->edges = (array) $graph->edges;
        $this->drift = $graph->drift;
    }

    /** Palette entries grouped by section. @return array<string, array<string, mixed>> */
    #[Computed]
    public function palette(): array
    {
        $grouped = [];
        foreach (NodeRegistry::library() as $key => $entry) {
            $grouped[$entry['group']][$key] = $entry;
        }
        ksort($grouped);

        return $grouped;
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function selectedNode(): ?array
    {
        foreach ($this->nodes as $n) {
            if (($n['id'] ?? null) === $this->selectedNodeId) {
                return $n;
            }
        }

        return null;
    }

    /** Compiled Caddy routes for the live preview. @return array<int, array<string, mixed>> */
    #[Computed]
    public function compiledRoutes(): array
    {
        return CaddyCompiler::compile($this->nodes, $this->edges);
    }

    public function addNode(string $type, int $x = 160, int $y = 120): void
    {
        $entry = NodeRegistry::library()[$type] ?? null;
        if (! $entry) {
            return;
        }

        $settings = [];
        foreach ($entry['settings'] ?? [] as $key => $def) {
            $settings[$key] = $def['default'] ?? '';
        }

        $id = 'n_'.bin2hex(random_bytes(4));
        $this->nodes[] = [
            'id'       => $id,
            'type'     => $type,
            'position' => ['x' => $x, 'y' => $y],
            'settings' => $settings,
        ];
        $this->selectedNodeId = $id;
        $this->persist();
    }

    public function moveNode(string $id, int $x, int $y): void
    {
        foreach ($this->nodes as $i => $n) {
            if (($n['id'] ?? null) === $id) {
                $this->nodes[$i]['position'] = ['x' => $x, 'y' => $y];
                break;
            }
        }
        $this->persist();
    }

    public function removeNode(string $id): void
    {
        $this->nodes = array_values(array_filter(
            $this->nodes,
            fn ($n) => ($n['id'] ?? null) !== $id
        ));
        $this->edges = array_values(array_filter(
            $this->edges,
            fn ($e) => ($e['from_node'] ?? null) !== $id && ($e['to_node'] ?? null) !== $id
        ));
        if ($this->selectedNodeId === $id) {
            $this->selectedNodeId = null;
        }
        $this->persist();
    }

    public function connect(string $fromNode, string $fromSocket, string $toNode, string $toSocket): void
    {
        if ($fromNode === $toNode) {
            return;
        }

        // A non-"many" input socket holds one wire · replace any existing.
        $many = $this->socketIsMany($toNode, $toSocket);
        if (! $many) {
            $this->edges = array_values(array_filter(
                $this->edges,
                fn ($e) => ! (($e['to_node'] ?? null) === $toNode && ($e['to_socket'] ?? null) === $toSocket)
            ));
        }

        $this->edges[] = [
            'id'          => 'e_'.bin2hex(random_bytes(4)),
            'from_node'   => $fromNode,
            'from_socket' => $fromSocket,
            'to_node'     => $toNode,
            'to_socket'   => $toSocket,
        ];
        $this->persist();
    }

    public function disconnect(string $edgeId): void
    {
        $this->edges = array_values(array_filter(
            $this->edges,
            fn ($e) => ($e['id'] ?? null) !== $edgeId
        ));
        $this->persist();
    }

    public function updatedNodes(): void
    {
        // wire:model edits to node settings flow through here.
        $this->persist();
    }

    public function apply(CaddyClient $client): void
    {
        $graph = $this->graph();
        if (! $graph) {
            return;
        }
        if (! $client->reachable()) {
            $this->status = 'Caddy admin API not reachable.';

            return;
        }

        $graph->apply($client);
        $this->drift = $graph->checkDrift($client);
        $this->status = 'Applied '.count($this->compiledRoutes).' route(s).';
    }

    public function refreshDrift(CaddyClient $client): void
    {
        $graph = $this->graph();
        if (! $graph || ! $client->reachable()) {
            $this->status = 'Caddy admin API not reachable.';

            return;
        }
        $this->drift = $graph->checkDrift($client);
        $this->status = $this->drift['in_sync'] ? 'In sync with Caddy.' : 'Drift detected.';
    }

    protected function graph(): ?CaddyGraph
    {
        return $this->graphId ? CaddyGraph::find($this->graphId) : null;
    }

    protected function persist(): void
    {
        $graph = $this->graph();
        if ($graph) {
            $graph->forceFill(['nodes' => $this->nodes, 'edges' => $this->edges])->save();
        }
    }

    protected function socketIsMany(string $nodeId, string $socket): bool
    {
        foreach ($this->nodes as $n) {
            if (($n['id'] ?? null) !== $nodeId) {
                continue;
            }
            $entry = NodeRegistry::library()[$n['type'] ?? ''] ?? null;

            return ! empty($entry['inputs'][$socket]['many']);
        }

        return false;
    }

    public function render()
    {
        return view('caddy-studio::livewire.caddy-builder');
    }
}
