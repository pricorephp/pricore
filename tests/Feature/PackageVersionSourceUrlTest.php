<?php

use App\Domains\Package\Contracts\Data\PackageVersionData;
use App\Models\Package;
use App\Models\PackageVersion;

it('links Bitbucket SSH host source urls to the Bitbucket web ui', function () {
    $version = PackageVersion::factory()->for(Package::factory()->create(['repository_uuid' => null]))->create([
        'version' => '1.0.0',
        'source_url' => 'git@ssh.bitbucket.org:acme/app.git',
        'source_reference' => 'abc123',
        'source_tag' => 'v1.0.0',
    ]);

    $data = PackageVersionData::fromModel($version);

    expect($data->commitUrl)->toBe('https://bitbucket.org/acme/app/src/abc123')
        ->and($data->tagUrl)->toBe('https://bitbucket.org/acme/app/src/v1.0.0');
});

it('removes only the .git suffix from https source urls', function (string $sourceUrl, string $commitUrl) {
    $version = PackageVersion::factory()->for(Package::factory()->create(['repository_uuid' => null]))->create([
        'version' => '1.0.0',
        'source_url' => $sourceUrl,
        'source_reference' => 'abc123',
    ]);

    expect(PackageVersionData::fromModel($version)->commitUrl)->toBe($commitUrl);
})->with([
    ['https://git.example.com/acme/digit.git', 'https://git.example.com/acme/digit/tree/abc123'],
    ['https://git.example.com/acme/audit', 'https://git.example.com/acme/audit/tree/abc123'],
    ['https://github.com/acme/toolkit.git', 'https://github.com/acme/toolkit/tree/abc123'],
]);
