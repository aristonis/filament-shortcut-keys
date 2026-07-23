<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortcut_map_selections', function (Blueprint $table): void {
            $table->id();

            // A selection always has an owner, so both columns are non-nullable. Capped at
            // 191 chars to stay within MySQL's utf8mb4 index-length limit.
            $table->string('authenticatable_type', 191);
            $table->string('authenticatable_id', 191);

            $table->string('panel_id', 191);
            $table->foreignId('map_id')->constrained('shortcut_maps')->cascadeOnDelete();
            $table->timestamps();

            // Exactly one active map per owner per panel.
            $table->unique(
                ['authenticatable_type', 'authenticatable_id', 'panel_id'],
                'shortcut_map_selections_owner_panel_unique',
            );

            // Postgres does not auto-index FK columns; without this the cascade delete and
            // the fork's redundant-map cleanup would full-scan selections by map_id.
            $table->index('map_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortcut_map_selections');
    }
};
