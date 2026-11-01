<?php

use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Exceptions\GitProviderRateLimitedException;
use App\Domains\Repository\Jobs\Middleware\ReleaseWhenRateLimited;
use App\Domains\Repository\Jobs\SyncRefJob;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->job = Mockery::mock(SyncRefJob::class);
    $this->job->releasedFor = null;
    $this->job->ran = false;
    $this->job->shouldReceive('release')->andReturnUsing(function (int $delay) {
        $this->job->releasedFor = $delay;
    });

    $this->middleware = new ReleaseWhenRateLimited('repository-uuid');
});

it('releases the job until the rate limit resets', function () {
    $this->freezeSecond();

    $this->middleware->handle($this->job, function () {
        throw new GitProviderException('Failed to fetch file content', previous: new GitProviderRateLimitedException(120));
    });

    expect($this->job->releasedFor)->toBe(120)
        ->and(Cache::get('git-provider-rate-limit:repository-uuid'))->toBe(now()->getTimestamp() + 120);
});

it('releases other jobs of the repository without running them while the limit lasts', function () {
    $this->freezeSecond();
    Cache::put('git-provider-rate-limit:repository-uuid', now()->getTimestamp() + 90, 90);

    $this->middleware->handle($this->job, fn ($job) => $job->ran = true);

    expect($this->job->ran)->toBeFalse()
        ->and($this->job->releasedFor)->toBe(90);
});

it('runs the job once the limit has reset', function () {
    Cache::put('git-provider-rate-limit:repository-uuid', now()->getTimestamp() - 1, 60);

    $this->middleware->handle($this->job, fn ($job) => $job->ran = true);

    expect($this->job->ran)->toBeTrue()
        ->and($this->job->releasedFor)->toBeNull();
});

it('rethrows other failures', function () {
    $this->middleware->handle($this->job, function () {
        throw new GitProviderException('Not found');
    });
})->throws(GitProviderException::class, 'Not found');
