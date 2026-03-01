<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gitlab_id')->nullable()->unique()->after('github_nickname');
            $table->text('gitlab_token')->nullable()->after('gitlab_id');
            $table->string('gitlab_nickname')->nullable()->after('gitlab_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gitlab_id', 'gitlab_token', 'gitlab_nickname']);
        });
    }
};
