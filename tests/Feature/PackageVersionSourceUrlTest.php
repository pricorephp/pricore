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
