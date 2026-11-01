<?php

use App\Domains\Repository\Contracts\Enums\GitProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
        'ssh://git@bitbucket.org:' => 'ssh://git@ssh.bitbucket.org:',
    ];

    public function up(): void
    {
        foreach (self::REPLACEMENTS as $old => $new) {
            DB::table('package_versions')
                ->where('source_url', 'like', $old.'%')
                ->chunkById(500, function ($versions) use ($old, $new): void {
                    foreach ($versions as $version) {
                        // Composer revalidates metadata with If-Modified-Since, which is
                        // answered from updated_at; without a bump it keeps the old address.
                        DB::table('package_versions')
                            ->where('uuid', $version->uuid)
                            ->update([
                                'source_url' => $new.substr($version->source_url, strlen($old)),
                                'updated_at' => now(),
                            ]);
                    }
                }, 'uuid');

            // Bare clones only live for the duration of a sync batch, so the next sync
            // clones from the new address without dropping one that is still in use.
            DB::table('repositories')
                ->where('provider', GitProvider::Git->value)
                ->where('repo_identifier', 'like', $old.'%')
                ->chunkById(500, function ($repositories) use ($old, $new): void {
                    foreach ($repositories as $repository) {
                        DB::table('repositories')
                            ->where('uuid', $repository->uuid)
                            ->update(['repo_identifier' => $new.substr($repository->repo_identifier, strlen($old))]);
                    }
                }, 'uuid');
        }
    }

    public function down(): void
    {
        // Not reversible — the old SSH host no longer accepts connections.
    }
};
