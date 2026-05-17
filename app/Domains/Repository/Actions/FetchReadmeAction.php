<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use Illuminate\Support\Facades\Log;

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
            $contents = $provider->getFileContent($ref, $filename);

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
