<?php

use App\Domains\Package\Contracts\Data\PackageVersionDetailData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\Package;
use App\Models\PackageVersion;

it('resolves relative README links against the package directory', function () {
    $package = Package::factory()->atPath('packages/billing')->create();
    $version = PackageVersion::factory()->forPackage($package)->atPath('packages/billing')->create([
        'source_reference' => 'abc123',
        'readme' => "![logo](docs/logo.png)\n\n[guide](GUIDE.md)",
    ]);

    $detail = PackageVersionDetailData::fromModel($version, GitProvider::GitHub, 'acme/mono');

    expect($detail->readmeHtml)
        ->toContain('https://raw.githubusercontent.com/acme/mono/abc123/packages/billing/docs/logo.png')
        ->toContain('https://github.com/acme/mono/blob/abc123/packages/billing/GUIDE.md');
});
