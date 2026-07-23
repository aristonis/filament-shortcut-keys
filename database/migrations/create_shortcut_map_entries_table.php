<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortcut_map_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('map_id')->constrained('shortcut_maps')->cascadeOnDelete();
            $table->string('target');
            $table->string('letter')->nullable();
            $table->boolean('disabled')->default(false);

            // UNIQUE(map_id, target) also serves map_id-only lookups via its leftmost
            // prefix, so no standalone map_id index is needed here.
            $table->unique(['map_id', 'target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortcut_map_entries');
    }
};
