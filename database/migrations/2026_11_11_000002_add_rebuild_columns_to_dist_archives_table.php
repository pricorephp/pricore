<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dist_archives', function (Blueprint $table) {
            $table->timestamp('file_removed_at')->nullable()->after('last_downloaded_at');
            $table->timestamp('rebuild_failed_at')->nullable()->after('file_removed_at');
            $table->string('source_path')->nullable()->after('source_reference');
        });

        // The directory at each archive's own commit was never stored; the
        // version's current one is right unless the package moved since
        DB::table('dist_archives')->update([
            'source_path' => DB::raw('(select source_path from package_versions where package_versions.uuid = dist_archives.package_version_uuid)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('dist_archives', function (Blueprint $table) {
            $table->dropColumn(['file_removed_at', 'rebuild_failed_at', 'source_path']);
        });
    }
};
