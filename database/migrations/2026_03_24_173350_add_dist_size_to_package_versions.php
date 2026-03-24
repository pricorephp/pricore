<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('dist_size')->nullable()->after('dist_path');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('package_versions', function (Blueprint $table) {
            $table->dropColumn('dist_size');
        });
    }

    private function backfill(): void
    {
        $disk = Storage::disk(config('pricore.dist.disk'));

        DB::table('package_versions')
            ->whereNotNull('dist_path')
            ->whereNull('dist_size')
            ->chunkById(100, function ($versions) use ($disk) {
                foreach ($versions as $version) {
                    try {
                        $size = $disk->size($version->dist_path);

                        DB::table('package_versions')
                            ->where('uuid', $version->uuid)
                            ->update(['dist_size' => $size]);
                    } catch (Throwable) {
                        // File may not exist on disk — skip silently
                    }
                }
            }, 'uuid');
    }
};
