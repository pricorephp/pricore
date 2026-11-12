<?php

namespace App\Domains\Repository\Contracts\Interfaces;

use App\Domains\Repository\Services\GitRemotes\ResolvedGitRemote;

interface GitRemoteProbeInterface
{
    /**
     * Where an HTTP remote redirects its ref advertisement to, or null when it
     * answers the request itself or cannot be reached.
     */
    public function redirectLocation(ResolvedGitRemote $gitRemote): ?string;
}
