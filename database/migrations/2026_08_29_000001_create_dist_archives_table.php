<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dist_archives', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('package_uuid')->constrained('packages', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('package_version_uuid')->constrained('package_versions', 'uuid')->cascadeOnDelete();
            $table->string('source_reference');
            $table->string('path');
            $table->string('shasum')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('detached_at')->nullable();
            $table->timestamps();

            // Named explicitly: the generated name exceeds the 63-byte identifier
            // limit PostgreSQL silently truncates at.
            $table->unique(['package_version_uuid', 'source_reference'], 'dist_archives_version_reference_unique');

            // MySQL indexes foreign keys automatically, PostgreSQL and SQLite do not.
            $table->index('package_uuid');
            $table->index('detached_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dist_archives');
    }
};
