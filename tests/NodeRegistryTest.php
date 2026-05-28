<?php

use LoggedCloud\CaddyStudio\Models\CaddyGraph;
use LoggedCloud\CaddyStudio\Nodes\NodeRegistry;
use LoggedCloud\CaddyStudio\Tests\TestCase;

uses(TestCase::class);

it('registers the builtin nodes on boot', function () {
    $library = NodeRegistry::library();

    expect($library)->toHaveKeys([
        'server', 'route.host', 'upstream', 'lb', 'redirect',
        'matcher.path', 'matcher.method', 'matcher.header', 'matcher.query',
        'file_server', 'static_response', 'fastcgi', 'subroute',
        'encode', 'headers', 'rewrite', 'basic_auth', 'rate_limit',
        'tls.duckdns',
    ]);
});

it('populates the load balancer policy options from config', function () {
    $entry = NodeRegistry::library()['lb'];

    expect($entry['settings']['policy']['options'])->toContain('round_robin', 'least_conn', 'ip_hash');
});

it('persists and recompiles a graph through the model', function () {
    $graph = CaddyGraph::create([
        'name'  => 'live',
        'nodes' => [
            ['id' => 'r', 'type' => 'route.host', 'settings' => ['hosts' => 'studio.logged.cloud']],
            ['id' => 'u', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.200:8107']],
        ],
        'edges' => [
            ['from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target'],
        ],
    ]);

    expect($graph->compileRoutes()[0]['match'][0]['host'])->toBe(['studio.logged.cloud']);
});

it('finds the active graph', function () {
    CaddyGraph::create(['name' => 'draft', 'is_active' => false, 'nodes' => [], 'edges' => []]);
    CaddyGraph::create(['name' => 'live', 'is_active' => true, 'nodes' => [], 'edges' => []]);

    expect(CaddyGraph::active()->name)->toBe('live');
});
