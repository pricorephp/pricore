<?php

namespace Tests;

use App\Domains\Mirror\Contracts\Interfaces\HostResolverInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeHostResolver;

abstract class TestCase extends BaseTestCase
{
    protected FakeHostResolver $hostResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostResolver = new FakeHostResolver;
        $this->app->instance(HostResolverInterface::class, $this->hostResolver);
    }
}
