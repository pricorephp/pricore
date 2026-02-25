<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add dev- prefix to branch versions for Composer compatibility.
     */
    public function up(): void
    {
        DB::table('package_versions')
            ->where('normalized_version', 'like', 'dev-%')
            ->where('version', 'not like', 'dev-%')
            ->update(['version' => DB::raw("CONCAT('dev-', version)")]);
    }

    /**
     * Remove dev- prefix from branch versions.
     */
    public function down(): void
    {
        DB::table('package_versions')
            ->where('version', 'like', 'dev-%')
            ->update(['version' => DB::raw("REPLACE(version, 'dev-', '')")]);
    }
};
