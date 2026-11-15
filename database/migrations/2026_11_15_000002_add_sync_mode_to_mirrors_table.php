<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mirrors', function (Blueprint $table) {
            $table->string('sync_mode')->default('full')->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('mirrors', function (Blueprint $table) {
            $table->dropColumn('sync_mode');
        });
    }
};
