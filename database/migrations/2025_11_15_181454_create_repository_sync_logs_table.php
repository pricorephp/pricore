<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('repository_sync_logs', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('repository_uuid')->constrained('repositories', 'uuid')->cascadeOnDelete();
            $table->string('status'); // pending, success, failed
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('details')->nullable();
            $table->unsignedInteger('versions_added')->default(0);
            $table->unsignedInteger('versions_updated')->default(0);
            $table->timestamps();

            $table->index(['repository_uuid', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repository_sync_logs');
    }
};
