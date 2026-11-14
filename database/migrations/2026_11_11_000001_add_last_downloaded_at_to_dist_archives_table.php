<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dist_archives', function (Blueprint $table) {
            $table->timestamp('last_downloaded_at')->nullable()->after('detached_at');
        });
    }

    public function down(): void
    {
        Schema::table('dist_archives', function (Blueprint $table) {
            $table->dropColumn('last_downloaded_at');
        });
    }
};
