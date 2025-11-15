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
        Schema::create('organization_git_credentials', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('organization_uuid')->constrained('organizations', 'uuid')->cascadeOnDelete();
            $table->string('provider'); // github, gitlab, bitbucket, git
            $table->text('credentials'); // encrypted JSON
            $table->timestamps();

            $table->unique(['organization_uuid', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_git_credentials');
    }
};
