<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_tokens', function (Blueprint $table) {
            $table->index('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('access_tokens', function (Blueprint $table) {
            $table->dropIndex(['token_hash']);
        });
    }
};
