<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mirrors', function (Blueprint $table) {
            $table->boolean('lazy_dist')->default(false)->after('mirror_dist');
        });
    }

    public function down(): void
    {
        Schema::table('mirrors', function (Blueprint $table) {
            $table->dropColumn('lazy_dist');
        });
    }
};
