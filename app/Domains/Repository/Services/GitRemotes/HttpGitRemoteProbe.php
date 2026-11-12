<?php

namespace App\Domains\Repository\Services\GitRemotes;

use App\Domains\Repository\Contracts\Interfaces\GitRemoteProbeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class HttpGitRemoteProbe implements GitRemoteProbeInterface
{
    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function redirectLocation(ResolvedGitRemote $gitRemote): ?string
    {
        $curlResolveEntry = $gitRemote->curlResolveEntry();

        try {
            $response = Http::connectTimeout(10)
                ->timeout(20)
                ->withUserAgent('git/2 (Pricore)')
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => $curlResolveEntry === null ? [] : [CURLOPT_RESOLVE => [$curlResolveEntry]],
                ])
                ->get($gitRemote->refAdvertisementUrl());
        } catch (ConnectionException) {
            // git reports an unreachable remote in its own words
            return null;
        }

        if (! in_array($response->status(), self::REDIRECT_STATUSES, true)) {
            return null;
        }

        $location = $response->header('Location');

        return $location === '' ? null : $location;
    }
}
