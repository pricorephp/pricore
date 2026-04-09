<?php

use App\Domains\Release\Actions\FetchLatestReleasesAction;
use App\Domains\Release\Contracts\Data\ReleaseInfoData;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    $this->fetchLatestReleasesAction = app(FetchLatestReleasesAction::class);
});

function fakeReleasesResponse(array $releases): void
{
    Http::fake([
        'api.github.com/repos/pricorephp/pricore/releases*' => Http::response($releases, 200),
    ]);
}

it('fetches releases, caches them, and detects an outdated version', function () {
    config()->set('app.version', '1.0.0');

    fakeReleasesResponse([
        [
            'name' => 'v1.2.0',
            'tag_name' => 'v1.2.0',
            'html_url' => 'https://github.com/pricorephp/pricore/releases/tag/v1.2.0',
            'published_at' => '2026-04-01T12:00:00Z',
            'body' => "## Features\n\n- Adds release notes viewer",
            'draft' => false,
            'prerelease' => false,
        ],
        [
            'name' => 'v1.0.0',
            'tag_name' => 'v1.0.0',
            'html_url' => 'https://github.com/pricorephp/pricore/releases/tag/v1.0.0',
            'published_at' => '2026-03-01T12:00:00Z',
            'body' => 'Initial release',
            'draft' => false,
            'prerelease' => false,
        ],
    ]);

    $info = $this->fetchLatestReleasesAction->handle();

    expect($info)->toBeInstanceOf(ReleaseInfoData::class);
    expect($info->currentVersion)->toBe('1.0.0');
    expect($info->latestVersion)->toBe('1.2.0');
    expect($info->isOutdated)->toBeTrue();
    expect($info->releases)->toHaveCount(2);
    expect($info->releases[0]->bodyHtml)->toContain('<h2>Features</h2>');

    // Second call must come from cache, not GitHub.
    $this->fetchLatestReleasesAction->handle();
    Http::assertSentCount(1);
});

it('reports not outdated when running the latest version', function () {
    config()->set('app.version', '1.2.0');

    fakeReleasesResponse([
        [
            'name' => 'v1.2.0',
            'tag_name' => 'v1.2.0',
            'html_url' => 'https://github.com/pricorephp/pricore/releases/tag/v1.2.0',
            'published_at' => '2026-04-01T12:00:00Z',
            'body' => 'Latest',
            'draft' => false,
            'prerelease' => false,
        ],
    ]);

    $info = $this->fetchLatestReleasesAction->handle();

    expect($info->isOutdated)->toBeFalse();
    expect($info->currentVersion)->toBe('1.2.0');
    expect($info->latestVersion)->toBe('1.2.0');
});

it('filters out drafts and prereleases', function () {
    config()->set('app.version', '1.0.0');

    fakeReleasesResponse([
        [
            'name' => 'v1.3.0-beta.1',
            'tag_name' => 'v1.3.0-beta.1',
            'html_url' => 'https://github.com/pricorephp/pricore/releases/tag/v1.3.0-beta.1',
            'published_at' => '2026-04-05T12:00:00Z',
            'body' => 'Beta',
            'draft' => false,
            'prerelease' => true,
        ],
        [
            'name' => 'v1.2.0',
            'tag_name' => 'v1.2.0',
            'html_url' => 'https://github.com/pricorephp/pricore/releases/tag/v1.2.0',
            'published_at' => '2026-04-01T12:00:00Z',
            'body' => 'Stable',
            'draft' => false,
            'prerelease' => false,
        ],
        [
            'name' => 'draft',
            'tag_name' => 'v1.4.0',
            'html_url' => 'https://github.com/pricorephp/pricore/releases/tag/v1.4.0',
            'published_at' => null,
            'body' => 'Draft',
            'draft' => true,
            'prerelease' => false,
        ],
    ]);

    $info = $this->fetchLatestReleasesAction->handle();

    expect($info->releases)->toHaveCount(1);
    expect($info->latestVersion)->toBe('1.2.0');
});

it('returns null and does not throw when GitHub fails', function () {
    config()->set('app.version', '1.0.0');

    Http::fake([
        'api.github.com/repos/pricorephp/pricore/releases*' => Http::response([], 503),
    ]);

    $info = $this->fetchLatestReleasesAction->handle();

    expect($info)->toBeNull();
});

it('honors the cooldown after a failed fetch', function () {
    config()->set('app.version', '1.0.0');

    Http::fake([
        'api.github.com/repos/pricorephp/pricore/releases*' => Http::response([], 503),
    ]);

    $this->fetchLatestReleasesAction->handle();
    $this->fetchLatestReleasesAction->handle();

    Http::assertSentCount(1);
});

it('returns release info with null current version when APP_VERSION is unset', function () {
    config()->set('app.version', null);

    fakeReleasesResponse([
        [
            'name' => 'v1.2.0',
            'tag_name' => 'v1.2.0',
            'html_url' => 'https://github.com/pricorephp/pricore/releases/tag/v1.2.0',
            'published_at' => '2026-04-01T12:00:00Z',
            'body' => 'Latest',
            'draft' => false,
            'prerelease' => false,
        ],
    ]);

    $info = $this->fetchLatestReleasesAction->handle();

    expect($info->currentVersion)->toBeNull();
    expect($info->isOutdated)->toBeFalse();
});

it('sends the proper headers to GitHub', function () {
    config()->set('app.version', '1.0.0');
    fakeReleasesResponse([]);

    $this->fetchLatestReleasesAction->handle();

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), 'api.github.com/repos/pricorephp/pricore/releases')
            && $request->hasHeader('Accept', 'application/vnd.github+json')
            && $request->hasHeader('User-Agent', 'Pricore');
    });
});
