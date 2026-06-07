<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchReadmeAction
{
    protected const CANDIDATE_FILENAMES = [
        'README.md',
        'readme.md',
        'Readme.md',
        'README.markdown',
        'readme.markdown',
        'README',
        'readme',
    ];

    protected const MAX_BYTES = 512 * 1024;

    public function handle(GitProviderInterface $provider, string $ref): ?string
    {
        foreach (self::CANDIDATE_FILENAMES as $filename) {
            try {
                $contents = $provider->getFileContent($ref, $filename);
            } catch (Throwable $e) {
                // A failing provider call (rate-limit, 5xx, auth blip) should not
                // abort the surrounding sync — the README is non-essential. Bail
                // the probe loop too: remaining candidates would hit the same
                // failure and just waste API budget.
                Log::warning('Failed to fetch README candidate', [
                    'repository' => $provider->getRepositoryIdentifier(),
                    'ref' => $ref,
                    'filename' => $filename,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }

            if ($contents === null) {
                continue;
            }

            if (strlen($contents) > self::MAX_BYTES) {
                Log::info('Skipped README that exceeds the size cap', [
                    'repository' => $provider->getRepositoryIdentifier(),
                    'ref' => $ref,
                    'filename' => $filename,
                    'size_bytes' => strlen($contents),
                ]);

                return null;
            }

            return $contents;
        }

        return null;
    }
}
