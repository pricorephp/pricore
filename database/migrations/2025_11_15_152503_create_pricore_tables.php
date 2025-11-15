<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', static function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignUuid('owner_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('organization_users', static function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('organization_uuid')->constrained('organizations', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('role', 50);
            $table->timestamps();

            $table->unique(['organization_uuid', 'user_uuid']);
        });

        Schema::create('repositories', static function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('organization_uuid')->constrained('organizations', 'uuid')->cascadeOnDelete();
            $table->string('name');
            $table->string('provider', 50);           // github/gitlab/bitbucket/git
            $table->string('repo_identifier');        // "org/repo" or full URL
            $table->string('default_branch')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status', 50)->nullable(); // ok/failed/pending
            $table->timestamps();
        });

        Schema::create('packages', static function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('organization_uuid')->constrained('organizations', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('repository_uuid')->nullable()->constrained('repositories', 'uuid')->nullOnDelete();
            $table->string('name'); // composer package name
            $table->text('description')->nullable();
            $table->string('type', 100)->nullable();
            $table->string('visibility', 50)->default('private');
            $table->boolean('is_proxy')->default(false);
            $table->timestamps();

            $table->unique(['organization_uuid', 'name']);
        });

        Schema::create('package_versions', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('package_uuid')->constrained('packages', 'uuid')->cascadeOnDelete();

            $table->string('version');
            $table->string('normalized_version');

            $table->json('composer_json');
            $table->string('source_url')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('dist_url')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            $table->unique(['package_uuid', 'version']);
        });

        Schema::create('access_tokens', static function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('organization_uuid')->nullable()->constrained('organizations', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('user_uuid')->nullable()->constrained('users', 'uuid')->nullOnDelete();

            $table->string('name')->nullable();
            $table->string('token_hash');      // save hash
            $table->json('scopes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_package_access');
        Schema::dropIfExists('access_tokens');
        Schema::dropIfExists('package_versions');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('repositories');
        Schema::dropIfExists('organization_users');
        Schema::dropIfExists('organizations');
    }
};
