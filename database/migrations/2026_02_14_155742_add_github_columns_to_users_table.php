<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('github_id')->nullable()->unique()->after('email');
            $table->text('github_token')->nullable()->after('github_id');
            $table->string('github_nickname')->nullable()->after('github_token');
            $table->string('avatar_url')->nullable()->after('github_nickname');
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['github_id', 'github_token', 'github_nickname', 'avatar_url']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
