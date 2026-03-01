<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('organization_uuid')->constrained('organizations', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('actor_uuid')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->string('type');
            $table->string('subject_type')->nullable();
            $table->uuid('subject_uuid')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_uuid', 'created_at']);
            $table->index(['subject_type', 'subject_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
