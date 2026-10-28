<?php

use App\Domains\Repository\Contracts\Enums\GitProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Bitbucket Cloud stops accepting SSH on bitbucket.org on 10 December 2026; SSH moves
 * to ssh.bitbucket.org with the same host keys. Stored SSH addresses are rewritten so
 * existing versions keep a working source URL and generic Git repositories keep syncing.
 */
return new class extends Migration
{
    private const REPLACEMENTS = [
        'git@bitbucket.org:' => 'git@ssh.bitbucket.org:',
        'ssh://git@bitbucket.org/' => 'ssh://git@ssh.bitbucket.org/',
    ];

    public function up(): void
    {
        foreach (self::REPLACEMENTS as $old => $new) {
            DB::table('package_versions')
                ->where('source_url', 'like', $old.'%')
                ->chunkById(500, function ($versions) use ($old, $new): void {
                    foreach ($versions as $version) {
                        DB::table('package_versions')
                            ->where('uuid', $version->uuid)
                            ->update(['source_url' => $new.substr($version->source_url, strlen($old))]);
                    }
                }, 'uuid');

            DB::table('repositories')
                ->where('provider', GitProvider::Git->value)
                ->where('repo_identifier', 'like', $old.'%')
                ->chunkById(500, function ($repositories) use ($old, $new): void {
                    foreach ($repositories as $repository) {
                        DB::table('repositories')
                            ->where('uuid', $repository->uuid)
                            ->update(['repo_identifier' => $new.substr($repository->repo_identifier, strlen($old))]);

                        // The bare clone still has the old address as its origin. Dropping it
                        // makes the next sync clone again from the new address.
                        File::deleteDirectory(storage_path("app/git-clones/{$repository->uuid}"));
                    }
                }, 'uuid');
        }
    }

    public function down(): void
    {
        // Not reversible — the old SSH host no longer accepts connections.
    }
};
