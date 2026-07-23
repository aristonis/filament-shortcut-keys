<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortcut_maps', function (Blueprint $table): void {
            $table->id();
            $table->string('panel_id', 191);
            $table->string('type');

            // Polymorphic owner as explicit string columns: host apps may key users by
            // int/uuid/ulid, and the core treats the owner id as an opaque string. System
            // maps have no owner, so both columns are nullable. Capped at 191 chars to stay
            // within MySQL's utf8mb4 index-length limit.
            $table->string('authenticatable_type', 191)->nullable();
            $table->string('authenticatable_id', 191)->nullable();

            $table->json('modifiers')->nullable();
            $table->unsignedInteger('version')->default(1);

            // Nullable-marker trick: set to panel_id for the panel's default system map,
            // NULL otherwise. A UNIQUE index over a nullable column enforces "one default
            // per panel" on MySQL/pgsql/sqlite alike (MySQL permits multiple NULLs), which
            // a partial/filtered WHERE index cannot do portably (MySQL has none).
            $table->string('default_marker')->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortcut_maps');
    }
};
