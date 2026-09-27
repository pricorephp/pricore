<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();

    $this->owner = User::factory()->create();
    $this->organization = Organization::factory()->create(['slug' => 'acme', 'owner_uuid' => $this->owner->uuid]);
    $this->package = Package::factory()->forOrganization($this->organization)->artifact()->create(['name' => 'acme/moodle-plugin']);

    $this->makeToken = function (?array $scopes = [TokenScope::Read->value, TokenScope::Write->value], ?User $user = null): string {
        $plainToken = Str::random(64);
        $factory = AccessToken::factory()->withPlainToken($plainToken)->neverExpires()->state(['scopes' => $scopes]);

        ($user ? $factory->forUser($user) : $factory->forOrganization($this->organization))->create();

        return $plainToken;
    };

    $this->makeUpload = function (array $composerJson = ['name' => 'acme/moodle-plugin', 'version' => '1.0.0']): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'pricore-upload-');
        createTestZip($path, ['composer.json' => json_encode($composerJson), 'version.php' => '<?php']);

        return new UploadedFile($path, 'build.zip', 'application/zip', null, true);
    };

    $this->upload = fn (?string $plainToken, array $data) => $this
        ->withHeaders($plainToken ? ['Authorization' => "Bearer {$plainToken}"] : [])
        ->post('/acme/api/packages/upload', $data);
});

it('publishes a version with a token that can publish', function () {
    $upload = ($this->makeUpload)();
    $shasum = sha1_file($upload->getPathname());

    ($this->upload)(($this->makeToken)(), ['archive' => $upload])
        ->assertCreated()
        ->assertExactJson([
            'result' => 'added',
            'package' => 'acme/moodle-plugin',
            'version' => '1.0.0',
            'version_normalized' => '1.0.0.0',
            'dist' => [
                'url' => url("/acme/dists/acme/moodle-plugin/1.0.0/{$shasum}.zip"),
                'shasum' => $shasum,
                'size' => $upload->getSize(),
            ],
        ]);
});

it('serves the uploaded version to Composer', function () {
    $plainToken = ($this->makeToken)();
    $upload = ($this->makeUpload)();
    $contents = file_get_contents($upload->getPathname());
    $shasum = sha1($contents);

    ($this->upload)($plainToken, ['archive' => $upload])->assertCreated();

    $metadata = $this->withHeaders(['Authorization' => "Bearer {$plainToken}"])
        ->getJson('/acme/p2/acme/moodle-plugin.json')
        ->assertOk()
        ->json('packages.acme/moodle-plugin.0');

    expect($metadata['version'])->toBe('1.0.0')
        ->and($metadata)->not->toHaveKey('source')
        ->and($metadata['dist'])->toBe([
            'type' => 'zip',
            'url' => url("/acme/dists/acme/moodle-plugin/1.0.0/{$shasum}.zip"),
            'reference' => $shasum,
            'shasum' => $shasum,
        ]);

    $download = $this->withHeaders(['Authorization' => "Bearer {$plainToken}"])
        ->get("/acme/dists/acme/moodle-plugin/1.0.0/{$shasum}.zip")
        ->assertOk();

    expect($download->streamedContent())->toBe($contents);
});

it('reports an identical re-upload as unchanged', function () {
    $plainToken = ($this->makeToken)();
    $upload = ($this->makeUpload)();

    ($this->upload)($plainToken, ['archive' => $upload])->assertCreated();
    ($this->upload)($plainToken, ['archive' => $upload])->assertOk()->assertJsonPath('result', 'unchanged');
});

it('rejects tokens that cannot publish', function (?array $scopes) {
    ($this->upload)(($this->makeToken)($scopes), ['archive' => ($this->makeUpload)()])
        ->assertForbidden()
        ->assertJsonPath('message', 'This token cannot publish packages. Create a token with publishing enabled.');

    expect($this->package->versions()->count())->toBe(0);
})->with([
    'tokens from before scopes were enforced' => [null],
    'read-only tokens' => [[TokenScope::Read->value]],
]);

it('requires a token', function () {
    ($this->upload)(null, ['archive' => ($this->makeUpload)()])->assertUnauthorized();
});

it('rejects tokens of another organization', function () {
    $otherOrganization = Organization::factory()->create();
    $plainToken = Str::random(64);
    AccessToken::factory()->forOrganization($otherOrganization)->withPlainToken($plainToken)->neverExpires()
        ->withScopes([TokenScope::Read->value, TokenScope::Write->value])->create();

    ($this->upload)($plainToken, ['archive' => ($this->makeUpload)()])->assertUnauthorized();
});

it('only lets personal tokens publish while their user can manage packages', function (OrganizationRole $role, int $status) {
    $user = User::factory()->create();
    $this->organization->members()->attach($user->uuid, ['uuid' => (string) Str::uuid(), 'role' => $role->value]);

    ($this->upload)(($this->makeToken)(user: $user), ['archive' => ($this->makeUpload)()])->assertStatus($status);
})->with([
    'member' => [OrganizationRole::Member, 403],
    'admin' => [OrganizationRole::Admin, 201],
]);

it('does not create packages', function () {
    ($this->upload)(($this->makeToken)(), ['archive' => ($this->makeUpload)(['name' => 'symfony/console', 'version' => '9.9.9'])])
        ->assertNotFound()
        ->assertJsonPath('message', 'Package symfony/console does not exist. An admin can create it by uploading its first version in Pricore.');

    expect(Package::query()->where('name', 'symfony/console')->exists())->toBeFalse();
});

it('does not publish into packages synced from a repository', function () {
    Package::factory()->forOrganization($this->organization)->create(['name' => 'acme/synced']);

    ($this->upload)(($this->makeToken)(), ['archive' => ($this->makeUpload)(['name' => 'acme/synced', 'version' => '9.9.9'])])
        ->assertStatus(409);
});

it('refuses to replace a published release', function () {
    $plainToken = ($this->makeToken)();

    ($this->upload)($plainToken, ['archive' => ($this->makeUpload)()])->assertCreated();
    ($this->upload)($plainToken, ['archive' => ($this->makeUpload)(['name' => 'acme/moodle-plugin', 'version' => '1.0.0', 'description' => 'changed'])])
        ->assertStatus(409);
});

it('answers validation errors as JSON', function () {
    ($this->upload)(($this->makeToken)(), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('archive');
});

it('answers unusable archives as JSON', function () {
    $path = tempnam(sys_get_temp_dir(), 'pricore-upload-');
    file_put_contents($path, 'not a zip');

    ($this->upload)(($this->makeToken)(), ['archive' => new UploadedFile($path, 'build.zip', null, null, true)])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The file is not a valid zip archive.');
});

it('rejects archives above the configured size', function () {
    config(['pricore.uploads.max_size' => 1]);
    $upload = UploadedFile::fake()->create('build.zip', 2048, 'application/zip');

    ($this->upload)(($this->makeToken)(), ['archive' => $upload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['archive' => 'The archive may not be larger than 1 MB.']);
});

it('rate limits uploads per token', function () {
    config(['pricore.uploads.rate_limit_per_minute' => 2]);
    $plainToken = ($this->makeToken)();

    ($this->upload)($plainToken, [])->assertUnprocessable();
    ($this->upload)($plainToken, [])->assertUnprocessable();
    ($this->upload)($plainToken, [])->assertTooManyRequests();
});
