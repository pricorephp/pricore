<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_downloads', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('organization_uuid')->constrained('organizations', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('package_uuid')->nullable()->constrained('packages', 'uuid')->nullOnDelete();
            $table->string('package_name');
            $table->string('version');
            $table->timestamp('downloaded_at');
            $table->timestamps();

            $table->index(['organization_uuid', 'package_name']);
            $table->index(['package_uuid', 'downloaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_downloads');
    }
};
