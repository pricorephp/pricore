<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_git_credentials', function (Blueprint $table) {
            $table->string('name')->nullable()->after('provider');

            // Keeps an index on user_uuid for its foreign key once the unique index is gone
            $table->index(['user_uuid', 'provider']);
        });

        Schema::table('user_git_credentials', function (Blueprint $table) {
            $table->dropUnique(['user_uuid', 'provider']);
        });

        Schema::table('repositories', function (Blueprint $table) {
            $table->foreignUuid('git_credential_uuid')
                ->nullable()
                ->after('credential_user_uuid')
                ->constrained('user_git_credentials', 'uuid')
                ->nullOnDelete();
        });

        DB::table('repositories')
            ->whereNotNull('credential_user_uuid')
            ->orderBy('uuid')
            ->each(function (object $repository): void {
                $credentialUuid = DB::table('user_git_credentials')
                    ->where('user_uuid', $repository->credential_user_uuid)
                    ->where('provider', $repository->provider)
                    ->value('uuid');

                if ($credentialUuid) {
                    DB::table('repositories')
                        ->where('uuid', $repository->uuid)
                        ->update(['git_credential_uuid' => $credentialUuid]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('git_credential_uuid');
        });

        Schema::table('user_git_credentials', function (Blueprint $table) {
            $table->unique(['user_uuid', 'provider']);
        });

        Schema::table('user_git_credentials', function (Blueprint $table) {
            $table->dropIndex(['user_uuid', 'provider']);
            $table->dropColumn('name');
        });
    }
};
