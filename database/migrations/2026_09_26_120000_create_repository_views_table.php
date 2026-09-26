<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_views', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('repository_uuid')->constrained('repositories', 'uuid')->cascadeOnDelete();
            $table->unsignedInteger('view_count')->default(1);
            $table->timestamp('last_viewed_at');

            $table->unique(['user_uuid', 'repository_uuid']);
            $table->index(['user_uuid', 'last_viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_views');
    }
};
