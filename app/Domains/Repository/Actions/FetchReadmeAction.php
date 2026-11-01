<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchReadmeAction
{
    /**
     * In order of preference, compared case-insensitively against the directory listing.
     */
    protected const CANDIDATE_FILENAMES = [
        'README.md',
        'README.markdown',
        'README',
    ];

    protected const MAX_BYTES = 512 * 1024;

    public function handle(GitProviderInterface $provider, string $ref, string $path = ''): ?string
    {
        $path = trim($path, '/');

        try {
            // Most packages use README.md, so try it before paying for a listing
            $contents = $provider->getFileContent($ref, self::join($path, self::CANDIDATE_FILENAMES[0]));

            if ($contents === null) {
                $filename = $this->findReadmeFilename($provider, $ref, $path);

                $contents = $filename === null || $filename === self::CANDIDATE_FILENAMES[0]
                    ? null
                    : $provider->getFileContent($ref, self::join($path, $filename));
            }
        } catch (Throwable $e) {
            // A failing provider call (rate-limit, 5xx, auth blip) should not
            // abort the surrounding sync — the README is non-essential.
            Log::warning('Failed to fetch README', [
                'repository' => $provider->getRepositoryIdentifier(),
                'ref' => $ref,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($contents === null) {
            return null;
        }

        if (strlen($contents) > self::MAX_BYTES) {
            Log::info('Skipped README that exceeds the size cap', [
                'repository' => $provider->getRepositoryIdentifier(),
                'ref' => $ref,
                'path' => $path,
                'size_bytes' => strlen($contents),
            ]);

            return null;
        }

        return $contents;
    }

    /**
     * One directory listing replaces probing every remaining candidate filename,
     * which matters once a monorepo multiplies the lookups per ref.
     */
    protected function findReadmeFilename(GitProviderInterface $provider, string $ref, string $path): ?string
    {
        $files = [];

        foreach ($provider->listDirectory($ref, $path) as $entry) {
            if ($entry['type'] === 'file') {
                $files[strtolower($entry['name'])] ??= $entry['name'];
            }
        }

        foreach (self::CANDIDATE_FILENAMES as $candidate) {
            $name = $files[strtolower($candidate)] ?? null;

            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    protected static function join(string $path, string $file): string
    {
        return $path === '' ? $file : "{$path}/{$file}";
    }
}
