<?php

use LoggedCloud\CaddyStudio\Support\CaddyCompiler;

it('merges wired matchers into the route match object', function () {
    $nodes = [
        ['id' => 'r',  'type' => 'route.host',     'settings' => ['hosts' => 'api.example.com']],
        ['id' => 'u',  'type' => 'upstream',       'settings' => ['dial' => '10.0.0.1:80']],
        ['id' => 'mp', 'type' => 'matcher.path',   'settings' => ['paths' => '/api/*']],
        ['id' => 'mm', 'type' => 'matcher.method', 'settings' => ['methods' => 'get, post']],
    ];
    $edges = [
        ['from_node' => 'u',  'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target'],
        ['from_node' => 'mp', 'from_socket' => 'matcher',  'to_node' => 'r', 'to_socket' => 'matchers'],
        ['from_node' => 'mm', 'from_socket' => 'matcher',  'to_node' => 'r', 'to_socket' => 'matchers'],
    ];

    $match = CaddyCompiler::compile($nodes, $edges)[0]['match'][0];

    expect($match)->toBe([
        'host'   => ['api.example.com'],
        'path'   => ['/api/*'],
        'method' => ['GET', 'POST'],
    ]);
});

it('chains middleware before the terminal handler in order', function () {
    $nodes = [
        ['id' => 'r',  'type' => 'route.host', 'settings' => ['hosts' => 'a.com']],
        ['id' => 'en', 'type' => 'encode',     'settings' => ['gzip' => true, 'zstd' => true]],
        ['id' => 'hd', 'type' => 'headers',    'settings' => ['name' => 'X-Foo', 'value' => 'bar']],
        ['id' => 'u',  'type' => 'upstream',   'settings' => ['dial' => '10.0.0.1:80']],
    ];
    $edges = [
        ['from_node' => 'en', 'from_socket' => 'target',   'to_node' => 'r',  'to_socket' => 'target'],
        ['from_node' => 'hd', 'from_socket' => 'target',   'to_node' => 'en', 'to_socket' => 'next'],
        ['from_node' => 'u',  'from_socket' => 'upstream', 'to_node' => 'hd', 'to_socket' => 'next'],
    ];

    $handle = CaddyCompiler::compile($nodes, $edges)[0]['handle'];

    expect(array_column($handle, 'handler'))->toBe(['encode', 'headers', 'reverse_proxy']);
});

it('compiles a file server terminal', function () {
    $nodes = [
        ['id' => 'r', 'type' => 'route.host',  'settings' => ['hosts' => 'static.example.com']],
        ['id' => 'f', 'type' => 'file_server', 'settings' => ['root' => '/srv/site', 'browse' => true]],
    ];
    $edges = [['from_node' => 'f', 'from_socket' => 'target', 'to_node' => 'r', 'to_socket' => 'target']];

    $h = CaddyCompiler::compile($nodes, $edges)[0]['handle'][0];

    expect($h['handler'])->toBe('file_server')
        ->and($h['root'])->toBe('/srv/site')
        ->and($h['browse'])->toBeInstanceOf(stdClass::class);
});

it('compiles a fastcgi (php) terminal', function () {
    $nodes = [
        ['id' => 'r', 'type' => 'route.host', 'settings' => ['hosts' => 'php.example.com']],
        ['id' => 'p', 'type' => 'fastcgi',    'settings' => ['dial' => 'app:9000']],
    ];
    $edges = [['from_node' => 'p', 'from_socket' => 'target', 'to_node' => 'r', 'to_socket' => 'target']];

    $h = CaddyCompiler::compile($nodes, $edges)[0]['handle'][0];

    expect($h['handler'])->toBe('reverse_proxy')
        ->and($h['transport']['protocol'])->toBe('fastcgi')
        ->and($h['upstreams'])->toBe([['dial' => 'app:9000']]);
});

it('adds active health checks to a load balancer', function () {
    $nodes = [
        ['id' => 'r',  'type' => 'route.host', 'settings' => ['hosts' => 'a.com']],
        ['id' => 'lb', 'type' => 'lb',         'settings' => ['policy' => 'round_robin', 'health_path' => '/up', 'health_interval' => '10s']],
        ['id' => 'u',  'type' => 'upstream',   'settings' => ['dial' => '10.0.0.1:80']],
    ];
    $edges = [
        ['from_node' => 'lb', 'from_socket' => 'target',   'to_node' => 'r',  'to_socket' => 'target'],
        ['from_node' => 'u',  'from_socket' => 'upstream', 'to_node' => 'lb', 'to_socket' => 'upstreams'],
    ];

    $h = CaddyCompiler::compile($nodes, $edges)[0]['handle'][0];

    expect($h['health_checks']['active'])->toBe(['path' => '/up', 'interval' => '10s']);
});

it('compiles the carla websocket subroute', function () {
    // Outer route on the host, splitting websocket upgrades to the Vite dev
    // server (https, skip-verify) from normal HTTP to nginx.
    $nodes = [
        ['id' => 'outer', 'type' => 'route.host',     'settings' => ['hosts' => 'carla-photo.duckdns.org']],
        ['id' => 'sub',   'type' => 'subroute',       'settings' => []],
        ['id' => 'ws',    'type' => 'route.host',     'settings' => ['hosts' => '']],
        ['id' => 'wsm',   'type' => 'matcher.header', 'settings' => ['name' => 'Upgrade', 'value' => 'websocket']],
        ['id' => 'wsu',   'type' => 'upstream',       'settings' => ['dial' => '10.0.0.200:5173', 'skip_tls_verify' => true]],
        ['id' => 'def',   'type' => 'route.host',     'settings' => ['hosts' => '']],
        ['id' => 'defu',  'type' => 'upstream',       'settings' => ['dial' => '10.0.0.200:80']],
    ];
    $edges = [
        ['from_node' => 'sub',  'from_socket' => 'target',   'to_node' => 'outer', 'to_socket' => 'target'],
        ['from_node' => 'ws',   'from_socket' => 'route',    'to_node' => 'sub',   'to_socket' => 'routes'],
        ['from_node' => 'def',  'from_socket' => 'route',    'to_node' => 'sub',   'to_socket' => 'routes'],
        ['from_node' => 'wsm',  'from_socket' => 'matcher',  'to_node' => 'ws',    'to_socket' => 'matchers'],
        ['from_node' => 'wsu',  'from_socket' => 'upstream', 'to_node' => 'ws',    'to_socket' => 'target'],
        ['from_node' => 'defu', 'from_socket' => 'upstream', 'to_node' => 'def',   'to_socket' => 'target'],
    ];

    $routes = CaddyCompiler::compile($nodes, $edges);

    // The inner routes are not top-level.
    expect($routes)->toHaveCount(1)
        ->and($routes[0]['match'][0]['host'])->toBe(['carla-photo.duckdns.org']);

    $subroute = $routes[0]['handle'][0];
    expect($subroute['handler'])->toBe('subroute')
        ->and($subroute['routes'])->toHaveCount(2);

    // Websocket inner route · header matcher + skip-verify transport.
    $wsRoute = $subroute['routes'][0];
    expect($wsRoute['match'][0]['header'])->toBe(['Upgrade' => ['websocket']])
        ->and($wsRoute['handle'][0]['transport']['tls']['insecure_skip_verify'])->toBeTrue()
        ->and($wsRoute['handle'][0]['upstreams'])->toBe([['dial' => '10.0.0.200:5173']]);

    // Default inner route · no match, plain proxy to nginx.
    expect($subroute['routes'][1])->not->toHaveKey('match')
        ->and($subroute['routes'][1]['handle'][0]['upstreams'])->toBe([['dial' => '10.0.0.200:80']]);
});

it('compiles a duckdns dns-01 tls policy', function () {
    $nodes = [
        ['id' => 't', 'type' => 'tls.duckdns', 'settings' => [
            'subjects'  => 'carla-photo.duckdns.org, charles-jellyfin.duckdns.org',
            'api_token' => 'tok-123',
        ]],
    ];

    expect(CaddyCompiler::compileTls($nodes, []))->toBe([[
        'subjects' => ['carla-photo.duckdns.org', 'charles-jellyfin.duckdns.org'],
        'issuers'  => [[
            'module'     => 'acme',
            'challenges' => ['dns' => ['provider' => ['name' => 'duckdns', 'api_token' => 'tok-123']]],
        ]],
    ]]);
});

it('compiles a basic-auth protected upstream', function () {
    $nodes = [
        ['id' => 'r', 'type' => 'route.host', 'settings' => ['hosts' => 'secret.example.com']],
        ['id' => 'a', 'type' => 'basic_auth', 'settings' => ['username' => 'admin', 'password_hash' => '$2y$abc']],
        ['id' => 'u', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.1:80']],
    ];
    $edges = [
        ['from_node' => 'a', 'from_socket' => 'target',   'to_node' => 'r', 'to_socket' => 'target'],
        ['from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'a', 'to_socket' => 'next'],
    ];

    $handle = CaddyCompiler::compile($nodes, $edges)[0]['handle'];

    expect($handle[0]['handler'])->toBe('authentication')
        ->and($handle[0]['providers']['http_basic']['accounts'][0])->toBe([
            'username' => 'admin',
            'password' => '$2y$abc',
        ])
        ->and($handle[1]['handler'])->toBe('reverse_proxy');
});
