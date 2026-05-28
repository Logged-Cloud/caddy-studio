<?php

return [

    'table_prefix' => 'caddy_studio_',
    'connection'   => null,

    /*
     * Caddy admin API · the running server's REST endpoint. Everything the
     * studio applies or diffs against goes through here. On the logged.cloud
     * stack this is the shared ingress at http://10.0.0.208:2019.
     */
    'admin' => [
        'url'     => env('CADDY_ADMIN_URL', 'http://localhost:2019'),
        'timeout' => (int) env('CADDY_ADMIN_TIMEOUT', 10),
        /*
         * Which Caddy HTTP server (inside apps.http.servers) the compiled
         * routes are written to. Caddy's default server key is "srv0".
         */
        'server'  => env('CADDY_ADMIN_SERVER', 'srv0'),
    ],

    /*
     * Drift detection · a scheduled command compiles the active graph and
     * compares it against the live server config. It never auto-corrects; it
     * records a drift report you can surface in the UI and act on via Apply.
     */
    'drift' => [
        'enabled'  => env('CADDY_STUDIO_DRIFT', true),
        // How the scheduler runs the check · cron expression.
        'schedule' => env('CADDY_STUDIO_DRIFT_SCHEDULE', '*/5 * * * *'),
    ],

    /*
     * Load-balancing policies Caddy's reverse_proxy supports. Surfaced as the
     * options on the load-balancer node's `policy` setting.
     */
    'lb_policies' => [
        'round_robin'        => 'Round robin',
        'least_conn'         => 'Least connections',
        'random'             => 'Random',
        'random_choose'      => 'Random (choose 2, least loaded)',
        'first'              => 'First available',
        'ip_hash'            => 'IP hash (sticky by client IP)',
        'uri_hash'           => 'URI hash (sticky by path)',
        'header'             => 'Header hash (sticky by header)',
        'cookie'             => 'Cookie (sticky session cookie)',
    ],
];
