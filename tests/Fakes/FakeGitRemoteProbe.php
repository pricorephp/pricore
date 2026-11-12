<?php

namespace Tests\Fakes;

use App\Domains\Repository\Contracts\Interfaces\GitRemoteProbeInterface;
use App\Domains\Repository\Services\GitRemotes\ResolvedGitRemote;

class FakeGitRemoteProbe implements GitRemoteProbeInterface
{
    /** @var array<string, string> */
    private array $redirects = [];

    /** @var array<int, string> */
    public array $probedUrls = [];

    public function redirect(string $refAdvertisementUrl, string $location): void
    {
        $this->redirects[$refAdvertisementUrl] = $location;
    }

    public function redirectLocation(ResolvedGitRemote $gitRemote): ?string
    {
        $this->probedUrls[] = $gitRemote->refAdvertisementUrl();

        return $this->redirects[$gitRemote->refAdvertisementUrl()] ?? null;
    }
}
