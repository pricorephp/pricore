<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->foreignUuid('ssh_key_uuid')->nullable()->after('credential_user_uuid')
                ->constrained('organization_ssh_keys', 'uuid')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ssh_key_uuid');
        });
    }
};
