<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_advisories', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('advisory_id')->unique();
            $table->string('package_name')->index();
            $table->string('title');
            $table->string('link')->nullable();
            $table->string('cve')->nullable()->index();
            $table->text('affected_versions');
            $table->string('severity', 50)->index();
            $table->json('sources')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->string('composer_repository')->nullable();
            $table->timestamps();
        });

        Schema::create('security_advisory_matches', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('security_advisory_uuid')
                ->constrained('security_advisories', 'uuid')
                ->cascadeOnDelete();
            $table->foreignUuid('package_version_uuid')
                ->constrained('package_versions', 'uuid')
                ->cascadeOnDelete();
            $table->string('match_type', 50);
            $table->string('dependency_name')->nullable();
            $table->timestamps();

            $table->unique(
                ['security_advisory_uuid', 'package_version_uuid', 'match_type', 'dependency_name'],
                'advisory_match_unique'
            );
        });

        Schema::create('advisory_sync_metadata', function (Blueprint $table) {
            $table->id();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('last_updated_since')->nullable();
            $table->unsignedInteger('advisories_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_advisory_matches');
        Schema::dropIfExists('security_advisories');
        Schema::dropIfExists('advisory_sync_metadata');
    }
};
