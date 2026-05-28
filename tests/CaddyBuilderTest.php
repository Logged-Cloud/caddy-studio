<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Livewire\Livewire;
use LoggedCloud\CaddyStudio\Livewire\CaddyBuilder;
use LoggedCloud\CaddyStudio\Models\CaddyGraph;
use LoggedCloud\CaddyStudio\Support\CaddyClient;
use LoggedCloud\CaddyStudio\Tests\TestCase;

uses(TestCase::class);

function bindClient(array $responses): void
{
    $stack = HandlerStack::create(new MockHandler($responses));
    app()->instance(CaddyClient::class, new CaddyClient('http://caddy:2019', 'srv0', 10, new Client(['handler' => $stack])));
}

it('creates a starter graph on mount when none exists', function () {
    Livewire::test(CaddyBuilder::class)
        ->assertSet('graphId', fn ($id) => $id !== null);

    expect(CaddyGraph::count())->toBe(1)
        ->and(CaddyGraph::first()->is_active)->toBeTrue();
});

it('adds a node and persists it to the graph', function () {
    $component = Livewire::test(CaddyBuilder::class)
        ->call('addNode', 'upstream');

    $graphId = $component->get('graphId');
    $nodes = CaddyGraph::find($graphId)->nodes;

    expect(collect($nodes)->pluck('type'))->toContain('upstream');
});

it('wires a route to an upstream and compiles a live route', function () {
    $graph = CaddyGraph::create([
        'name' => 'g', 'is_active' => true,
        'nodes' => [
            ['id' => 'r', 'type' => 'route.host', 'position' => ['x' => 0, 'y' => 0], 'settings' => ['hosts' => 'a.com', 'terminal' => true]],
            ['id' => 'u', 'type' => 'upstream',   'position' => ['x' => 0, 'y' => 0], 'settings' => ['dial' => '10.0.0.1:80']],
        ],
        'edges' => [],
    ]);

    Livewire::test(CaddyBuilder::class, ['graphId' => $graph->id])
        ->call('connect', 'u', 'upstream', 'r', 'target');

    $compiled = $graph->fresh()->compileRoutes();
    expect($compiled[0]['handle'][0]['upstreams'])->toBe([['dial' => '10.0.0.1:80']]);
});

it('replaces a single-socket wire instead of stacking', function () {
    $graph = CaddyGraph::create([
        'name' => 'g', 'is_active' => true,
        'nodes' => [
            ['id' => 'r',  'type' => 'route.host', 'position' => ['x' => 0, 'y' => 0], 'settings' => ['hosts' => 'a.com']],
            ['id' => 'u1', 'type' => 'upstream',   'position' => ['x' => 0, 'y' => 0], 'settings' => ['dial' => '10.0.0.1:80']],
            ['id' => 'u2', 'type' => 'upstream',   'position' => ['x' => 0, 'y' => 0], 'settings' => ['dial' => '10.0.0.2:80']],
        ],
        'edges' => [],
    ]);

    Livewire::test(CaddyBuilder::class, ['graphId' => $graph->id])
        ->call('connect', 'u1', 'upstream', 'r', 'target')
        ->call('connect', 'u2', 'upstream', 'r', 'target');

    // The route's "target" is not a many-socket, so only the latest wire holds.
    $edges = $graph->fresh()->edges;
    $toTarget = array_values(array_filter($edges, fn ($e) => $e['to_socket'] === 'target'));
    expect($toTarget)->toHaveCount(1)
        ->and($toTarget[0]['from_node'])->toBe('u2');
});

it('stacks wires on a many-socket (load balancer upstreams)', function () {
    $graph = CaddyGraph::create([
        'name' => 'g', 'is_active' => true,
        'nodes' => [
            ['id' => 'lb', 'type' => 'lb',       'position' => ['x' => 0, 'y' => 0], 'settings' => ['policy' => 'round_robin']],
            ['id' => 'u1', 'type' => 'upstream', 'position' => ['x' => 0, 'y' => 0], 'settings' => ['dial' => '10.0.0.1:80']],
            ['id' => 'u2', 'type' => 'upstream', 'position' => ['x' => 0, 'y' => 0], 'settings' => ['dial' => '10.0.0.2:80']],
        ],
        'edges' => [],
    ]);

    Livewire::test(CaddyBuilder::class, ['graphId' => $graph->id])
        ->call('connect', 'u1', 'upstream', 'lb', 'upstreams')
        ->call('connect', 'u2', 'upstream', 'lb', 'upstreams');

    $toUp = array_filter($graph->fresh()->edges, fn ($e) => $e['to_socket'] === 'upstreams');
    expect($toUp)->toHaveCount(2);
});

it('removes a node and its connected edges', function () {
    $graph = CaddyGraph::create([
        'name' => 'g', 'is_active' => true,
        'nodes' => [
            ['id' => 'r', 'type' => 'route.host', 'position' => ['x' => 0, 'y' => 0], 'settings' => ['hosts' => 'a.com']],
            ['id' => 'u', 'type' => 'upstream',   'position' => ['x' => 0, 'y' => 0], 'settings' => ['dial' => '10.0.0.1:80']],
        ],
        'edges' => [
            ['id' => 'e1', 'from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target'],
        ],
    ]);

    Livewire::test(CaddyBuilder::class, ['graphId' => $graph->id])
        ->call('removeNode', 'u');

    $fresh = $graph->fresh();
    expect($fresh->nodes)->toHaveCount(1)
        ->and($fresh->edges)->toHaveCount(0);
});

it('applies the graph to caddy and stores the drift report', function () {
    bindClient([
        new Response(200, [], '{}'), // reachable() GET /config/
        new Response(200), // applyRoutes PATCH
        new Response(200, [], json_encode([[
            'match'    => [['host' => ['a.com']]],
            'handle'   => [['handler' => 'reverse_proxy', 'upstreams' => [['dial' => '10.0.0.1:80']]]],
            'terminal' => true,
        ]])), // checkDrift GET routes
    ]);

    $graph = CaddyGraph::create([
        'name' => 'g', 'is_active' => true,
        'nodes' => [
            ['id' => 'r', 'type' => 'route.host', 'position' => ['x' => 0, 'y' => 0], 'settings' => ['hosts' => 'a.com', 'terminal' => true]],
            ['id' => 'u', 'type' => 'upstream',   'position' => ['x' => 0, 'y' => 0], 'settings' => ['dial' => '10.0.0.1:80']],
        ],
        'edges' => [
            ['id' => 'e1', 'from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target'],
        ],
    ]);

    Livewire::test(CaddyBuilder::class, ['graphId' => $graph->id])
        ->call('apply')
        ->assertSet('drift.in_sync', true);

    expect($graph->fresh()->applied_at)->not->toBeNull();
});
