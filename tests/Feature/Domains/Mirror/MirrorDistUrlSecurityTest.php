<?php

use App\Domains\Mirror\Actions\DownloadMirrorDistAction;
use App\Domains\Mirror\Exceptions\MirrorDistDownloadException;
use App\Models\Mirror;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('rejects a private dist URL without making a request or storing an archive', function () {
    Storage::fake('local');
    Http::fake();

    $organization = Organization::factory()->create(['slug' => 'acme']);
    $mirror = Mirror::factory()->create([
        'organization_uuid' => $organization->uuid,
        'url' => 'https://registry.example.com',
    ]);
    $package = Package::factory()->withoutRepository()->create([
        'organization_uuid' => $organization->uuid,
        'mirror_uuid' => $mirror->uuid,
        'name' => 'vendor/package',
    ]);
    $version = PackageVersion::factory()->forPackage($package)->create([
        'version' => '1.0.0',
        'composer_json' => [
            'name' => 'vendor/package',
            'version' => '1.0.0',
            'dist' => [
                'reference' => 'abc123',
                'url' => 'http://169.254.169.254/latest/meta-data/iam/security-credentials/role',
            ],
        ],
    ]);

    expect(fn () => app(DownloadMirrorDistAction::class)->handle(
        $mirror,
        $version,
        $package,
        $organization->slug,
    ))->toThrow(MirrorDistDownloadException::class, 'unsafe URL');

    Http::assertNothingSent();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});
