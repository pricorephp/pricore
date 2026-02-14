<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_git_credentials', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('provider');
            $table->text('credentials');
            $table->timestamps();

            $table->unique(['user_uuid', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_git_credentials');
    }
};
