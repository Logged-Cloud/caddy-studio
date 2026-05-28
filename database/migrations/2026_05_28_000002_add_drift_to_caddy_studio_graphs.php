<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('caddy-studio.table_prefix', 'caddy_studio_');

        Schema::connection(config('caddy-studio.connection'))
            ->table($prefix.'graphs', function (Blueprint $table) {
                // Latest drift report (DriftDetector::compare output) + when it
                // was taken, so the UI can show "in sync" / "3 routes drifted"
                // without re-hitting Caddy on every render.
                $table->json('drift')->nullable();
                $table->timestamp('drift_checked_at')->nullable();
            });
    }

    public function down(): void
    {
        $prefix = config('caddy-studio.table_prefix', 'caddy_studio_');
        Schema::connection(config('caddy-studio.connection'))
            ->table($prefix.'graphs', function (Blueprint $table) {
                $table->dropColumn(['drift', 'drift_checked_at']);
            });
    }
};
