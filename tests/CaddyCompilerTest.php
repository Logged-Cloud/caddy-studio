<?php

use LoggedCloud\CaddyStudio\Support\CaddyCompiler;

it('compiles a host route wired to a single upstream', function () {
    // Models the live studio.logged.cloud route.
    $nodes = [
        ['id' => 'r', 'type' => 'route.host', 'settings' => ['hosts' => 'studio.logged.cloud', 'terminal' => true]],
        ['id' => 'u', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.200:8107']],
    ];
    $edges = [
        ['from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target'],
    ];

    expect(CaddyCompiler::compile($nodes, $edges))->toBe([
        [
            'match'    => [['host' => ['studio.logged.cloud']]],
            'handle'   => [['handler' => 'reverse_proxy', 'upstreams' => [['dial' => '10.0.0.200:8107']]]],
            'terminal' => true,
        ],
    ]);
});

it('splits a comma-separated host list into the match array', function () {
    $nodes = [
        ['id' => 'r', 'type' => 'route.host', 'settings' => ['hosts' => 'lauraaston.com, www.lauraaston.com']],
        ['id' => 'u', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.200:8101']],
    ];
    $edges = [['from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target']];

    $route = CaddyCompiler::compile($nodes, $edges)[0];

    expect($route['match'][0]['host'])->toBe(['lauraaston.com', 'www.lauraaston.com']);
});

it('adds a TLS skip-verify transport for https backends', function () {
    // Models the carla websocket upstream (insecure_skip_verify: true).
    $nodes = [
        ['id' => 'r', 'type' => 'route.host', 'settings' => ['hosts' => 'carla-photo.duckdns.org']],
        ['id' => 'u', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.200:5173', 'skip_tls_verify' => true]],
    ];
    $edges = [['from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target']];

    $handle = CaddyCompiler::compile($nodes, $edges)[0]['handle'][0];

    expect($handle['transport'])->toBe([
        'protocol' => 'http',
        'tls'      => ['insecure_skip_verify' => true],
    ]);
});

it('compiles a load balancer into a reverse_proxy with policy + many upstreams', function () {
    $nodes = [
        ['id' => 'r',  'type' => 'route.host', 'settings' => ['hosts' => 'app.example.com']],
        ['id' => 'lb', 'type' => 'lb',         'settings' => ['policy' => 'least_conn']],
        ['id' => 'u1', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.10:80']],
        ['id' => 'u2', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.11:80']],
    ];
    $edges = [
        ['from_node' => 'lb', 'from_socket' => 'target',   'to_node' => 'r',  'to_socket' => 'target'],
        ['from_node' => 'u1', 'from_socket' => 'upstream', 'to_node' => 'lb', 'to_socket' => 'upstreams'],
        ['from_node' => 'u2', 'from_socket' => 'upstream', 'to_node' => 'lb', 'to_socket' => 'upstreams'],
    ];

    $handle = CaddyCompiler::compile($nodes, $edges)[0]['handle'][0];

    expect($handle['handler'])->toBe('reverse_proxy')
        ->and($handle['load_balancing'])->toBe(['selection_policy' => ['policy' => 'least_conn']])
        ->and($handle['upstreams'])->toBe([
            ['dial' => '10.0.0.10:80'],
            ['dial' => '10.0.0.11:80'],
        ]);
});

it('compiles a redirect into a static_response handler', function () {
    $nodes = [
        ['id' => 'r',   'type' => 'route.host', 'settings' => ['hosts' => 'old.example.com']],
        ['id' => 'red', 'type' => 'redirect',   'settings' => ['to' => 'https://new.example.com', 'status' => '301']],
    ];
    $edges = [['from_node' => 'red', 'from_socket' => 'target', 'to_node' => 'r', 'to_socket' => 'target']];

    expect(CaddyCompiler::compile($nodes, $edges)[0]['handle'][0])->toBe([
        'handler'     => 'static_response',
        'status_code' => 301,
        'headers'     => ['Location' => ['https://new.example.com']],
    ]);
});

it('omits a route whose target is not wired', function () {
    $nodes = [['id' => 'r', 'type' => 'route.host', 'settings' => ['hosts' => 'orphan.example.com']]];

    expect(CaddyCompiler::compile($nodes, []))->toBe([]);
});

it('drops the terminal flag when the route is non-terminal', function () {
    $nodes = [
        ['id' => 'r', 'type' => 'route.host', 'settings' => ['hosts' => 'a.com', 'terminal' => false]],
        ['id' => 'u', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.1:80']],
    ];
    $edges = [['from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target']];

    expect(CaddyCompiler::compile($nodes, $edges)[0])->not->toHaveKey('terminal');
});

it('wraps routes in a server object with the configured listen address', function () {
    $nodes = [
        ['id' => 's', 'type' => 'server',     'settings' => ['listen' => ':8443']],
        ['id' => 'r', 'type' => 'route.host', 'settings' => ['hosts' => 'a.com']],
        ['id' => 'u', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.1:80']],
    ];
    $edges = [['from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target']];

    $server = CaddyCompiler::compileServer($nodes, $edges);

    expect($server['listen'])->toBe([':8443'])
        ->and($server['routes'])->toHaveCount(1);
});

it('defaults the listen address to :443 when there is no server node', function () {
    expect(CaddyCompiler::compileServer([], [])['listen'])->toBe([':443']);
});

it('compiles every route node in declaration order', function () {
    $nodes = [
        ['id' => 'r1', 'type' => 'route.host', 'settings' => ['hosts' => 'one.com']],
        ['id' => 'u1', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.1:80']],
        ['id' => 'r2', 'type' => 'route.host', 'settings' => ['hosts' => 'two.com']],
        ['id' => 'u2', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.2:80']],
    ];
    $edges = [
        ['from_node' => 'u1', 'from_socket' => 'upstream', 'to_node' => 'r1', 'to_socket' => 'target'],
        ['from_node' => 'u2', 'from_socket' => 'upstream', 'to_node' => 'r2', 'to_socket' => 'target'],
    ];

    $routes = CaddyCompiler::compile($nodes, $edges);

    expect($routes)->toHaveCount(2)
        ->and($routes[0]['match'][0]['host'])->toBe(['one.com'])
        ->and($routes[1]['match'][0]['host'])->toBe(['two.com']);
});
