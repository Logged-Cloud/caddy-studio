<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use LoggedCloud\CaddyStudio\Support\CaddyClient;

/**
 * Build a CaddyClient backed by a mocked Guzzle transport, returning the
 * client plus the array that captures every request it makes.
 *
 * @return array{0: CaddyClient, 1: array}
 */
function mockedClient(array $responses): array
{
    // ArrayObject so the captured history is shared by handle · a plain array
    // would be returned by value before any request has populated it.
    $history = new ArrayObject();
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    $http = new Client(['handler' => $stack]);

    return [new CaddyClient('http://caddy:2019', 'srv0', 10, $http), $history];
}

it('reads the live routes of the managed server', function () {
    [$client] = mockedClient([
        new Response(200, [], json_encode([['match' => [['host' => ['a.com']]]]])),
    ]);

    expect($client->routes())->toBe([['match' => [['host' => ['a.com']]]]]);
});

it('treats a null routes body as an empty list', function () {
    [$client] = mockedClient([new Response(200, [], 'null')]);

    expect($client->routes())->toBe([]);
});

it('PATCHes the full routes array on apply', function () {
    [$client, $history] = mockedClient([new Response(200)]);
    $routes = [['match' => [['host' => ['a.com']]]]];

    $client->applyRoutes($routes);

    $req = $history[0]['request'];
    expect($req->getMethod())->toBe('PATCH')
        ->and((string) $req->getUri()->getPath())->toBe('/config/apps/http/servers/srv0/routes')
        ->and(json_decode((string) $req->getBody(), true))->toBe($routes);
});

it('POSTs a single appended route', function () {
    [$client, $history] = mockedClient([new Response(200)]);

    $client->appendRoute(['match' => [['host' => ['b.com']]]]);

    expect($history[0]['request']->getMethod())->toBe('POST');
});

it('reports reachable false when the admin API errors', function () {
    [$client] = mockedClient([new Response(500)]);

    expect($client->reachable())->toBeFalse();
});

it('targets a non-default server name', function () {
    $history = [];
    $stack = \GuzzleHttp\HandlerStack::create(new MockHandler([new Response(200, [], 'null')]));
    $stack->push(Middleware::history($history));
    $client = new CaddyClient('http://caddy:2019', 'public', 10, new Client(['handler' => $stack]));

    $client->routes();

    expect((string) $history[0]['request']->getUri()->getPath())
        ->toBe('/config/apps/http/servers/public/routes');
});
