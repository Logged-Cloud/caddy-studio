<?php

use LoggedCloud\CaddyStudio\Support\DriftDetector;

function proxyRoute(string $host, string $dial): array
{
    return [
        'match'  => [['host' => [$host]]],
        'handle' => [['handler' => 'reverse_proxy', 'upstreams' => [['dial' => $dial]]]],
    ];
}

it('reports in sync when graph and live match', function () {
    $routes = [proxyRoute('a.com', '10.0.0.1:80'), proxyRoute('b.com', '10.0.0.2:80')];

    $report = DriftDetector::compare($routes, $routes);

    expect($report['in_sync'])->toBeTrue()
        ->and($report['only_in_graph'])->toBe([])
        ->and($report['only_in_live'])->toBe([]);
});

it('reports in sync regardless of route order', function () {
    $graph = [proxyRoute('a.com', '10.0.0.1:80'), proxyRoute('b.com', '10.0.0.2:80')];
    $live  = [proxyRoute('b.com', '10.0.0.2:80'), proxyRoute('a.com', '10.0.0.1:80')];

    expect(DriftDetector::compare($graph, $live)['in_sync'])->toBeTrue();
});

it('flags a route that exists in the graph but not live (needs apply)', function () {
    $graph = [proxyRoute('a.com', '10.0.0.1:80'), proxyRoute('new.com', '10.0.0.9:80')];
    $live  = [proxyRoute('a.com', '10.0.0.1:80')];

    $report = DriftDetector::compare($graph, $live);

    expect($report['in_sync'])->toBeFalse()
        ->and($report['only_in_graph'])->toBe(['new.com']);
});

it('flags an unmanaged route live but not in the graph', function () {
    $graph = [proxyRoute('a.com', '10.0.0.1:80')];
    $live  = [proxyRoute('a.com', '10.0.0.1:80'), proxyRoute('manual.com', '10.0.0.9:80')];

    $report = DriftDetector::compare($graph, $live);

    expect($report['in_sync'])->toBeFalse()
        ->and($report['only_in_live'])->toBe(['manual.com']);
});

it('flags a route whose upstream changed', function () {
    $graph = [proxyRoute('a.com', '10.0.0.1:80')];
    $live  = [proxyRoute('a.com', '10.0.0.99:80')];

    $report = DriftDetector::compare($graph, $live);

    expect($report['in_sync'])->toBeFalse()
        ->and($report['changed'])->toBe(['a.com']);
});
