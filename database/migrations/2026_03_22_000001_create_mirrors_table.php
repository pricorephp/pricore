<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mirrors', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('organization_uuid')->constrained('organizations', 'uuid')->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('auth_type', 50)->default('none');
            $table->text('auth_credentials')->nullable();
            $table->boolean('mirror_dist')->default(true);
            $table->string('sync_status', 50)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mirror_sync_logs', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('mirror_uuid')->constrained('mirrors', 'uuid')->cascadeOnDelete();
            $table->string('batch_id')->nullable();
            $table->string('status', 50);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('details')->nullable();
            $table->unsignedInteger('versions_added')->default(0);
            $table->unsignedInteger('versions_updated')->default(0);
            $table->unsignedInteger('versions_skipped')->default(0);
            $table->unsignedInteger('versions_failed')->default(0);
            $table->unsignedInteger('versions_removed')->default(0);
            $table->timestamps();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->foreignUuid('mirror_uuid')->nullable()->after('repository_uuid')->constrained('mirrors', 'uuid')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mirror_uuid');
        });

        Schema::dropIfExists('mirror_sync_logs');
        Schema::dropIfExists('mirrors');
    }
};
