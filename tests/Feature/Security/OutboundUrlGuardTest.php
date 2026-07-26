<?php

use App\Domains\Mirror\Contracts\Enums\MirrorAuthType;
use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\Http\OutboundUrlGuard;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

uses()->group('security', 'mirrors');

it('blocks urls that resolve into reserved ranges', function (string $url) {
    expect(app(OutboundUrlGuard::class)->allows($url))->toBeFalse();
})->with([
    'loopback' => 'http://127.0.0.1/packages.json',
    'loopback name' => 'http://localhost/packages.json',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'rfc1918 class a' => 'https://10.1.2.3/packages.json',
    'rfc1918 class b' => 'https://172.16.0.9/packages.json',
    'rfc1918 class c' => 'https://192.168.1.1/packages.json',
    'ipv6 loopback' => 'http://[::1]/packages.json',
    'unspecified' => 'http://0.0.0.0/packages.json',
]);

it('allows a public address', function () {
    expect(app(OutboundUrlGuard::class)->allows('https://93.184.216.34/packages.json'))->toBeTrue();
});

it('allows an internal host that has been explicitly permitted', function () {
    expect(app(OutboundUrlGuard::class)->allows('http://127.0.0.1/packages.json'))->toBeFalse();

    config()->set('pricore.outbound.allowed_hosts', ['127.0.0.1']);

    expect(app(OutboundUrlGuard::class)->allows('http://127.0.0.1/packages.json'))->toBeTrue();
});

it('matches subdomains of an allowed host', function () {
    config()->set('pricore.outbound.allowed_hosts', ['internal.example']);

    $guard = app(OutboundUrlGuard::class);

    expect($guard->hostFor('https://git.internal.example/acme/widgets.git'))->toBe('git.internal.example');
});

it('reads the host from git scp shorthand', function () {
    expect(app(OutboundUrlGuard::class)->hostFor('git@git.example.com:acme/widgets.git'))
        ->toBe('git.example.com');
});

it('rejects a mirror pointed at the cloud metadata endpoint', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);

    actingAs($user)
        ->post(route('organizations.settings.mirrors.store', $organization->slug), [
            'name' => 'Metadata',
            'url' => 'http://169.254.169.254/latest/meta-data/',
            'auth_type' => MirrorAuthType::None->value,
        ])
        ->assertSessionHasErrors('url');

    assertDatabaseCount('mirrors', 0);
});
