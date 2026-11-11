<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dist_archives', function (Blueprint $table) {
            $table->timestamp('file_removed_at')->nullable()->after('last_downloaded_at');
            $table->timestamp('rebuild_failed_at')->nullable()->after('file_removed_at');
        });
    }

    public function down(): void
    {
        Schema::table('dist_archives', function (Blueprint $table) {
            $table->dropColumn(['file_removed_at', 'rebuild_failed_at']);
        });
    }
};
