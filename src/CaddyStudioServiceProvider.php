<?php

namespace LoggedCloud\CaddyStudio;

use Illuminate\Support\ServiceProvider;
use LoggedCloud\CaddyStudio\Nodes\NodeRegistry;

class CaddyStudioServiceProvider extends ServiceProvider
{
    /** @var array<int, class-string<Nodes\CaddyNodeType>> */
    protected array $builtinNodes = [
        Nodes\Builtin\ServerNode::class,
        Nodes\Builtin\RouteHostNode::class,
        Nodes\Builtin\UpstreamNode::class,
        Nodes\Builtin\LoadBalancerNode::class,
        Nodes\Builtin\RedirectNode::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/caddy-studio.php', 'caddy-studio');
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
    }

    protected function registerBuiltinNodes(): void
    {
        foreach ($this->builtinNodes as $class) {
            NodeRegistry::register($class);
        }
    }
}
