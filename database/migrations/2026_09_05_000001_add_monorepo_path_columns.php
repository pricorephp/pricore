<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->json('package_paths')->nullable()->after('default_branch');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->string('source_path')->nullable()->after('repository_uuid');
        });

        Schema::table('package_versions', function (Blueprint $table) {
            $table->string('source_path')->nullable()->after('source_tag');
        });
    }

    public function down(): void
    {
        Schema::table('package_versions', function (Blueprint $table) {
            $table->dropColumn('source_path');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('source_path');
        });

        Schema::table('repositories', function (Blueprint $table) {
            $table->dropColumn('package_paths');
        });
    }
};
