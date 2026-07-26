<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every Composer request looks a token up by its hash. Without an index that is a
 * full table scan on the hottest query in the application, and nothing stopped two
 * rows sharing a hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->removeDuplicateHashes();

        Schema::table('access_tokens', function (Blueprint $table) {
            $table->unique('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('access_tokens', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
        });
    }

    /**
     * A duplicate can only exist if the same secret was issued twice, in which case
     * the tokens are interchangeable anyway — keep the oldest and drop the rest so
     * the constraint can be applied.
     */
    private function removeDuplicateHashes(): void
    {
        $duplicates = DB::table('access_tokens')
            ->select('token_hash')
            ->groupBy('token_hash')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('token_hash');

        foreach ($duplicates as $hash) {
            $keep = DB::table('access_tokens')
                ->where('token_hash', $hash)
                ->orderBy('created_at')
                ->value('uuid');

            DB::table('access_tokens')
                ->where('token_hash', $hash)
                ->where('uuid', '!=', $keep)
                ->delete();
        }
    }
};
