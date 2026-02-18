<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', static function (Blueprint $table) {
            $table->dropForeign(['repository_uuid']);
            $table->foreignUuid('repository_uuid')->nullable()->change()->constrained('repositories', 'uuid')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('packages', static function (Blueprint $table) {
            $table->dropForeign(['repository_uuid']);
            $table->foreignUuid('repository_uuid')->nullable()->change()->constrained('repositories', 'uuid')->nullOnDelete();
        });
    }
};
