<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use LoggedCloud\CaddyStudio\Models\CaddyGraph;
use LoggedCloud\CaddyStudio\Support\CaddyClient;
use LoggedCloud\CaddyStudio\Tests\TestCase;

uses(TestCase::class);

function clientReturning(array $responses, array &$history): CaddyClient
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new CaddyClient('http://caddy:2019', 'srv0', 10, new Client(['handler' => $stack]));
}

function studioGraph(): CaddyGraph
{
    return CaddyGraph::create([
        'name'      => 'live',
        'is_active' => true,
        'nodes'     => [
            ['id' => 'r', 'type' => 'route.host', 'settings' => ['hosts' => 'studio.logged.cloud']],
            ['id' => 'u', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.200:8107']],
        ],
        'edges' => [
            ['from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target'],
        ],
    ]);
}

it('applies the compiled routes and records the snapshot', function () {
    $history = [];
    $client = clientReturning([new Response(200)], $history);
    $graph = studioGraph();

    $graph->apply($client);

    expect($history[0]['request']->getMethod())->toBe('PATCH')
        ->and(json_decode((string) $history[0]['request']->getBody(), true)[0]['match'][0]['host'])
        ->toBe(['studio.logged.cloud'])
        ->and($graph->fresh()->applied_at)->not->toBeNull()
        ->and($graph->fresh()->applied_config[0]['match'][0]['host'])->toBe(['studio.logged.cloud']);
});

it('records an in-sync drift report when live matches the graph', function () {
    $history = [];
    $live = [[
        'match'    => [['host' => ['studio.logged.cloud']]],
        'handle'   => [['handler' => 'reverse_proxy', 'upstreams' => [['dial' => '10.0.0.200:8107']]]],
        'terminal' => true,
    ]];
    $client = clientReturning([new Response(200, [], json_encode($live))], $history);

    $report = studioGraph()->checkDrift($client);

    expect($report['in_sync'])->toBeTrue();
});

it('records drift when the live upstream differs', function () {
    $history = [];
    $live = [[
        'match'  => [['host' => ['studio.logged.cloud']]],
        'handle' => [['handler' => 'reverse_proxy', 'upstreams' => [['dial' => '10.0.0.200:9999']]]],
    ]];
    $client = clientReturning([new Response(200, [], json_encode($live))], $history);
    $graph = studioGraph();

    $report = $graph->checkDrift($client);

    expect($report['in_sync'])->toBeFalse()
        ->and($report['changed'])->toBe(['studio.logged.cloud'])
        ->and($graph->fresh()->drift['in_sync'])->toBeFalse()
        ->and($graph->fresh()->drift_checked_at)->not->toBeNull();
});
