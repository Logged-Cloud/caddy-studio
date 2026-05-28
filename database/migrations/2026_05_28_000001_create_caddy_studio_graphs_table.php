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
            ->create($prefix.'graphs', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->string('name');
                // Only one graph is the live source of truth at a time · the
                // one drift detection compares against and Apply pushes.
                $table->boolean('is_active')->default(false)->index();
                // Node + edge lists stored as JSON · a Caddy config is small
                // enough that adjacency tables would be overkill.
                $table->json('nodes');
                $table->json('edges');
                // Stamp of the last successful Apply + the JSON that was
                // pushed, so drift detection has a baseline even if the graph
                // has been edited since.
                $table->json('applied_config')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();
            });
    }

    public function down(): void
    {
        $prefix = config('caddy-studio.table_prefix', 'caddy_studio_');
        Schema::connection(config('caddy-studio.connection'))
            ->dropIfExists($prefix.'graphs');
    }
};
