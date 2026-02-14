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
        Schema::table('organization_git_credentials', function (Blueprint $table) {
            $table->foreignUuid('source_user_uuid')->nullable()->after('credentials')
                ->constrained('users', 'uuid')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_git_credentials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_user_uuid');
        });
    }
};
