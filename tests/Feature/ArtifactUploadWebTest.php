<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();

    $this->admin = User::factory()->create();
    $this->organization = Organization::factory()->create(['slug' => 'acme', 'owner_uuid' => $this->admin->uuid]);
    $this->organization->members()->attach($this->admin->uuid, ['uuid' => (string) Str::uuid(), 'role' => OrganizationRole::Admin->value]);

    $this->makeUpload = function (array $composerJson = ['name' => 'acme/legacy', 'version' => '1.0.0']): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'pricore-upload-');
        createTestZip($path, ['composer.json' => json_encode($composerJson)]);

        return new UploadedFile($path, 'legacy.zip', 'application/zip', null, true);
    };
});

it('lets admins create a package by uploading its first version', function () {
    $response = actingAs($this->admin)->post('/organizations/acme/packages/upload', [
        'archive' => ($this->makeUpload)(['name' => 'acme/legacy']),
        'version' => '2.1.0',
    ]);

    $package = Package::query()->where('name', 'acme/legacy')->firstOrFail();

    $response->assertRedirect(route('organizations.packages.show', [$this->organization, $package]))
        ->assertSessionHas('status', 'Package created with version 2.1.0.');

    expect($package->is_artifact)->toBeTrue()
        ->and($package->versions()->pluck('version')->all())->toBe(['2.1.0']);
});

it('uploads new versions from the package page', function () {
    $package = Package::factory()->forOrganization($this->organization)->artifact()->create(['name' => 'acme/legacy']);

    actingAs($this->admin)
        ->post("/organizations/acme/packages/{$package->uuid}/versions", ['archive' => ($this->makeUpload)()])
        ->assertRedirect()
        ->assertSessionHas('status', 'Version 1.0.0 published.');

    expect($package->versions()->count())->toBe(1);
});

it('shows why an upload was rejected on the archive field', function () {
    $package = Package::factory()->forOrganization($this->organization)->artifact()->create(['name' => 'acme/legacy']);

    actingAs($this->admin)
        ->post("/organizations/acme/packages/{$package->uuid}/versions", [
            'archive' => ($this->makeUpload)(['name' => 'acme/other', 'version' => '1.0.0']),
        ])
        ->assertSessionHasErrors(['archive' => 'The archive is for acme/other, not acme/legacy.']);
});

it('does not upload versions to packages synced from a repository', function () {
    $package = Package::factory()->forOrganization($this->organization)->create(['name' => 'acme/legacy']);

    actingAs($this->admin)
        ->post("/organizations/acme/packages/{$package->uuid}/versions", ['archive' => ($this->makeUpload)()])
        ->assertSessionHasErrors('archive');

    expect($package->versions()->count())->toBe(0);
});

it('does not upload versions to packages of another organization', function () {
    $package = Package::factory()->artifact()->create(['name' => 'acme/legacy']);

    actingAs($this->admin)
        ->post("/organizations/acme/packages/{$package->uuid}/versions", ['archive' => ($this->makeUpload)()])
        ->assertNotFound();
});

it('forbids members from uploading', function () {
    $member = User::factory()->create();
    $this->organization->members()->attach($member->uuid, ['uuid' => (string) Str::uuid(), 'role' => OrganizationRole::Member->value]);

    actingAs($member)
        ->post('/organizations/acme/packages/upload', ['archive' => ($this->makeUpload)()])
        ->assertForbidden();

    expect(Package::query()->count())->toBe(0);
});

it('offers uploads only to users who can manage packages', function (OrganizationRole $role, bool $canUpload) {
    $user = User::factory()->create();
    $this->organization->members()->attach($user->uuid, ['uuid' => (string) Str::uuid(), 'role' => $role->value]);
    $package = Package::factory()->forOrganization($this->organization)->artifact()->create();

    actingAs($user)
        ->get('/organizations/acme/packages')
        ->assertInertia(fn ($page) => $page->where('canUploadPackages', $canUpload));

    actingAs($user)
        ->get("/organizations/acme/packages/{$package->uuid}")
        ->assertInertia(fn ($page) => $page
            ->where('canUploadVersions', $canUpload)
            ->where('package.isArtifact', true));
})->with([
    'admin' => [OrganizationRole::Admin, true],
    'member' => [OrganizationRole::Member, false],
]);

it('does not offer version uploads for packages synced from a repository', function () {
    $package = Package::factory()->forOrganization($this->organization)->create();

    actingAs($this->admin)
        ->get("/organizations/acme/packages/{$package->uuid}")
        ->assertInertia(fn ($page) => $page->where('canUploadVersions', false));
});
