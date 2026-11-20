<?php

namespace Tests;

use App\Domains\Mirror\Contracts\Interfaces\HostResolverInterface;
use App\Domains\Repository\Contracts\Interfaces\GitRemoteProbeInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeGitRemoteProbe;
use Tests\Fakes\FakeHostResolver;

abstract class TestCase extends BaseTestCase
{
    protected FakeHostResolver $hostResolver;

    protected FakeGitRemoteProbe $gitRemoteProbe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostResolver = new FakeHostResolver;
        $this->app->instance(HostResolverInterface::class, $this->hostResolver);

        $this->gitRemoteProbe = new FakeGitRemoteProbe;
        $this->app->instance(GitRemoteProbeInterface::class, $this->gitRemoteProbe);
    }
}
