# Caddy Studio

Visual, node-based config editor for [Caddy](https://caddyserver.com). Drag a
domain to an upstream, split it through a load balancer, compile the graph to
Caddy admin-API JSON, apply it, and get alerted when the live server drifts
from what you designed.

Built on the same node-graph engine as
[`logged-cloud/page-studio`](https://github.com/Logged-Cloud/page-studio).

## Status

Phase 1 · the graph model + compiler. The graph compiles to the routes JSON
the Caddy admin API expects. The canvas UI, the admin client, Apply, and drift
detection land in later phases.

## How it works

A graph is a set of nodes and edges. Each **Route** node becomes one top-level
Caddy route; its handler is whatever you wire into its `target` input:

| Wire the route's target to | Result |
| --- | --- |
| an **Upstream** (`host:port`) | a one-backend `reverse_proxy` |
| a **Load Balancer** | a `reverse_proxy` with a policy + many backends |
| a **Redirect** | a `static_response` |

```php
use LoggedCloud\CaddyStudio\Support\CaddyCompiler;

$nodes = [
    ['id' => 'r', 'type' => 'route.host', 'settings' => ['hosts' => 'studio.logged.cloud']],
    ['id' => 'u', 'type' => 'upstream',   'settings' => ['dial' => '10.0.0.200:8107']],
];
$edges = [
    ['from_node' => 'u', 'from_socket' => 'upstream', 'to_node' => 'r', 'to_socket' => 'target'],
];

CaddyCompiler::compile($nodes, $edges);
// [
//   [
//     'match'    => [['host' => ['studio.logged.cloud']]],
//     'handle'   => [['handler' => 'reverse_proxy', 'upstreams' => [['dial' => '10.0.0.200:8107']]]],
//     'terminal' => true,
//   ],
// ]
```

## Install

```bash
composer require logged-cloud/caddy-studio
```

Publish the config and run the migration:

```bash
php artisan vendor:publish --tag=caddy-studio-config
php artisan vendor:publish --tag=caddy-studio-migrations
php artisan migrate
```

Point it at your running Caddy admin endpoint:

```
CADDY_ADMIN_URL=http://10.0.0.208:2019
```

## Tests

```bash
composer install
vendor/bin/pest
```
