<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Test-only table backing Tests\Support\Models\UuidOwner so a string-keyed owner can be
// exercised against the polymorphic map owner columns.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uuid_owners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uuid_owners');
    }
};
