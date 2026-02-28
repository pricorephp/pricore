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
            $table->unsignedInteger('versions_removed')->default(0)->after('versions_failed');
        });
    }

    public function down(): void
    {
        Schema::table('repository_sync_logs', function (Blueprint $table) {
            $table->dropColumn('versions_removed');
        });
    }
};
