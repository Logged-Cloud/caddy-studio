<?php

namespace LoggedCloud\CaddyStudio;

use Illuminate\Support\ServiceProvider;
use LoggedCloud\CaddyStudio\Nodes\NodeRegistry;

class CaddyStudioServiceProvider extends ServiceProvider
{
    /** @var array<int, class-string<Nodes\CaddyNodeType>> */
    protected array $builtinNodes = [
        // Core
        Nodes\Builtin\ServerNode::class,
        Nodes\Builtin\RouteHostNode::class,
        Nodes\Builtin\UpstreamNode::class,
        Nodes\Builtin\LoadBalancerNode::class,
        Nodes\Builtin\RedirectNode::class,
        // Matchers
        Nodes\Builtin\PathMatcherNode::class,
        Nodes\Builtin\MethodMatcherNode::class,
        Nodes\Builtin\HeaderMatcherNode::class,
        Nodes\Builtin\QueryMatcherNode::class,
        // Terminal handlers
        Nodes\Builtin\FileServerNode::class,
        Nodes\Builtin\StaticResponseNode::class,
        Nodes\Builtin\FastcgiNode::class,
        Nodes\Builtin\SubrouteNode::class,
        // Middleware
        Nodes\Builtin\EncodeNode::class,
        Nodes\Builtin\HeadersNode::class,
        Nodes\Builtin\RewriteNode::class,
        Nodes\Builtin\BasicAuthNode::class,
        Nodes\Builtin\RateLimitNode::class,
        // TLS
        Nodes\Builtin\TlsDuckdnsNode::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/caddy-studio.php', 'caddy-studio');

        $this->app->singleton(Support\CaddyClient::class, function () {
            return new Support\CaddyClient(
                baseUrl: (string) config('caddy-studio.admin.url'),
                server: (string) config('caddy-studio.admin.server', 'srv0'),
                timeout: (int) config('caddy-studio.admin.timeout', 10),
            );
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ApplyGraphCommand::class,
                Console\CheckDriftCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'caddy-studio');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/caddy-studio.php' => config_path('caddy-studio.php'),
        ], 'caddy-studio-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'caddy-studio-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/caddy-studio'),
        ], 'caddy-studio-views');

        $this->registerBuiltinNodes();
        $this->registerDriftSchedule();

        if (class_exists(\Livewire\Livewire::class) && $this->app->bound('livewire')) {
            \Livewire\Livewire::component('caddy-studio.caddy-builder', \LoggedCloud\CaddyStudio\Livewire\CaddyBuilder::class);
        }
    }

    protected function registerDriftSchedule(): void
    {
        if (! config('caddy-studio.drift.enabled', true)) {
            return;
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
            $schedule->command('caddy-studio:check-drift')
                ->cron((string) config('caddy-studio.drift.schedule', '*/5 * * * *'))
                ->withoutOverlapping();
        });
    }

    protected function registerBuiltinNodes(): void
    {
        foreach ($this->builtinNodes as $class) {
            NodeRegistry::register($class);
        }
    }
}
