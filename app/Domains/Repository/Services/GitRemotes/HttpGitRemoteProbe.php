<?php

namespace App\Domains\Repository\Services\GitRemotes;

use App\Domains\Repository\Contracts\Interfaces\GitRemoteProbeInterface;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class HttpGitRemoteProbe implements GitRemoteProbeInterface
{
    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function redirectLocation(ResolvedGitRemote $gitRemote): ?string
    {
        $curlResolveEntry = $gitRemote->curlResolveEntry();
        $status = null;
        $location = '';

        $pendingRequest = Http::connectTimeout(10)
            ->timeout(20)
            ->withUserAgent('git/2 (Pricore)')
            ->withOptions([
                'allow_redirects' => false,
                'curl' => $curlResolveEntry === null ? [] : [CURLOPT_RESOLVE => [$curlResolveEntry]],
                // The status and headers are all that is needed, so the transfer ends before the body
                'on_headers' => function (ResponseInterface $response) use (&$status, &$location): void {
                    $status = $response->getStatusCode();
                    $location = $response->getHeaderLine('Location');

                    throw new RuntimeException('The response headers were received.');
                },
            ]);

        // A remote may only redirect once it knows who is asking. The URL carries
        // credentials for this origin alone: the policy drops them when a redirect leaves it.
        if ($gitRemote->httpCredentials() !== null) {
            $pendingRequest = $pendingRequest->withBasicAuth(...$gitRemote->httpCredentials());
        }

        try {
            $response = $pendingRequest->get($gitRemote->refAdvertisementUrl());

            // Only reached when the transfer was not ended above, as with a faked response
            $status = $response->status();
            $location = $response->header('Location');
        } catch (Throwable) {
            // Either the transfer was ended above, or the remote cannot be reached,
            // which git reports in its own words
        }

        return in_array($status, self::REDIRECT_STATUSES, true) && $location !== '' ? $location : null;
    }
}
