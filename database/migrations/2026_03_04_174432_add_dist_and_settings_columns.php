<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_versions', function (Blueprint $table) {
            $table->string('dist_shasum')->nullable()->after('dist_url');
            $table->string('dist_path')->nullable()->after('dist_shasum');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedInteger('dist_keep_last_releases')->default(0)->after('is_proxy');
        });
    }

    public function down(): void
    {
        Schema::table('package_versions', function (Blueprint $table) {
            $table->dropColumn(['dist_shasum', 'dist_path']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('dist_keep_last_releases');
        });
    }
};
