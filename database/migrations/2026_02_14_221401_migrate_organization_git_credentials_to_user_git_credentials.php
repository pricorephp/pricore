<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $credentials = DB::table('organization_git_credentials')->get();

        foreach ($credentials as $credential) {
            $userUuid = $credential->source_user_uuid;

            // If no source user, assign to the organization owner
            if (! $userUuid) {
                $organization = DB::table('organizations')
                    ->where('uuid', $credential->organization_uuid)
                    ->first();

                $userUuid = $organization?->owner_uuid;
            }

            if (! $userUuid) {
                continue;
            }

            // Create user git credential if it doesn't already exist
            $exists = DB::table('user_git_credentials')
                ->where('user_uuid', $userUuid)
                ->where('provider', $credential->provider)
                ->exists();

            if (! $exists) {
                DB::table('user_git_credentials')->insert([
                    'uuid' => (string) Str::uuid(),
                    'user_uuid' => $userUuid,
                    'provider' => $credential->provider,
                    'credentials' => $credential->credentials,
                    'created_at' => $credential->created_at,
                    'updated_at' => $credential->updated_at,
                ]);
            }

            // Set credential_user_uuid on matching repositories
            DB::table('repositories')
                ->where('organization_uuid', $credential->organization_uuid)
                ->where('provider', $credential->provider)
                ->whereNull('credential_user_uuid')
                ->update(['credential_user_uuid' => $userUuid]);
        }

        Schema::dropIfExists('organization_git_credentials');
    }

    public function down(): void
    {
        // This migration is not reversible as the old table is dropped
    }
};
