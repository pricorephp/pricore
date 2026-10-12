<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_empty_refs', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('repository_uuid')->constrained('repositories', 'uuid')->cascadeOnDelete();
            $table->string('name');
            $table->string('commit');
            $table->timestamp('checked_at');

            $table->unique(['repository_uuid', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_empty_refs');
    }
};
