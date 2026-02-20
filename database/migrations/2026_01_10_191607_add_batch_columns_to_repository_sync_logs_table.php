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
        Schema::table('repository_sync_logs', function (Blueprint $table) {
            $table->string('batch_id')->nullable()->after('repository_uuid');
            $table->unsignedInteger('versions_skipped')->default(0)->after('versions_updated');
            $table->unsignedInteger('versions_failed')->default(0)->after('versions_skipped');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repository_sync_logs', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'versions_skipped', 'versions_failed']);
        });
    }
};
