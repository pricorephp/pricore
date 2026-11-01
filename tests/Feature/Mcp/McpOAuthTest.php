<?php

use App\Models\User;
use App\Providers\McpServiceProvider;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses()->group('mcp');

beforeEach(function () {
    $keyPath = sys_get_temp_dir().'/pricore-passport-test-keys';

    Passport::loadKeysFrom($keyPath);

    if (! file_exists("{$keyPath}/oauth-private.key")) {
        @mkdir($keyPath, 0700, true);
        Artisan::call('passport:keys', ['--force' => true]);
    }
});

function mcpRequest(string $method = 'tools/list'): array
{
    return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => []];
}

function registerMcpClient(string $redirectUri = 'http://localhost:33418/callback'): string
{
    return postJson('/oauth/register', [
        'client_name' => 'Claude Code',
        'redirect_uris' => [$redirectUri],
    ])->assertCreated()->json('client_id');
}

it('advertises the protected resource and authorization server', function () {
    getJson('/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertJson([
            'resource' => url('/mcp'),
            'authorization_servers' => [url('/')],
            'scopes_supported' => [Registrar::OAUTH_SCOPE],
        ]);

    getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJson([
            'authorization_endpoint' => url('/oauth/authorize'),
            'token_endpoint' => url('/oauth/token'),
            'registration_endpoint' => url('/oauth/register'),
            'code_challenge_methods_supported' => ['S256'],
        ]);
});

it('registers public clients dynamically', function () {
    $clientId = registerMcpClient();

    $client = Client::query()->findOrFail($clientId);

    expect($client->name)->toBe('Claude Code')
        ->and($client->confidential())->toBeFalse()
        ->and($client->redirect_uris)->toBe(['http://localhost:33418/callback']);
});

it('challenges unauthenticated MCP requests with the resource metadata', function () {
    postJson('/mcp', mcpRequest())
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');
});

it('rejects tokens without the MCP scope', function () {
    Passport::actingAs(User::factory()->create());

    postJson('/mcp', mcpRequest())->assertForbidden();
});

it('serves the tools to tokens with the MCP scope', function () {
    Passport::actingAs(User::factory()->create(), [Registrar::OAUTH_SCOPE]);

    postJson('/mcp', mcpRequest())
        ->assertOk()
        ->assertJsonFragment(['name' => 'list-organizations'])
        ->assertJsonFragment(['name' => 'create-access-token']);
});

it('sends guests to the login page before asking for consent', function () {
    $clientId = registerMcpClient();

    get('/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://localhost:33418/callback',
        'response_type' => 'code',
        'scope' => Registrar::OAUTH_SCOPE,
        'code_challenge' => Str::random(43),
        'code_challenge_method' => 'S256',
    ]))->assertRedirect(route('login'));
});

it('completes the authorization code flow and accepts the issued token', function () {
    $user = User::factory()->create();
    $redirectUri = 'http://localhost:33418/callback';
    $clientId = registerMcpClient($redirectUri);
    $codeVerifier = Str::random(64);
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

    $authToken = null;

    actingAs($user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => Registrar::OAUTH_SCOPE,
            'state' => 'state-123',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertInertia(function (AssertableInertia $page) use ($clientId, &$authToken) {
            $page->component('auth/authorize-mcp')
                ->where('client.id', $clientId)
                ->where('client.name', 'Claude Code')
                ->where('redirectHost', 'localhost:33418')
                ->where('approveUrl', url('/oauth/authorize'))
                ->where('denyUrl', url('/oauth/authorize'));

            $authToken = $page->toArray()['props']['authToken'];
        });

    $redirect = actingAs($user)
        ->post('/oauth/authorize', ['client_id' => $clientId, 'auth_token' => $authToken, 'state' => ''])
        ->assertRedirect()
        ->headers->get('Location');

    expect($redirect)->toStartWith($redirectUri);
    parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $query);
    expect($query['state'])->toBe('state-123');

    auth()->forgetGuards();

    $accessToken = postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'code_verifier' => $codeVerifier,
        'code' => $query['code'],
    ])->assertOk()->json('access_token');

    postJson('/mcp', mcpRequest(), ['Authorization' => "Bearer {$accessToken}"])
        ->assertOk()
        ->assertJsonFragment(['name' => 'list-organizations']);
});

it('returns to the client with an error when the user denies access', function () {
    $user = User::factory()->create();
    $redirectUri = 'http://localhost:33418/callback';
    $clientId = registerMcpClient($redirectUri);
    $authToken = null;

    actingAs($user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => Registrar::OAUTH_SCOPE,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', Str::random(64), true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]))
        ->assertInertia(function (AssertableInertia $page) use (&$authToken) {
            $authToken = $page->toArray()['props']['authToken'];
        });

    $redirect = actingAs($user)
        ->delete('/oauth/authorize', ['client_id' => $clientId, 'auth_token' => $authToken, 'state' => ''])
        ->assertRedirect()
        ->headers->get('Location');

    expect($redirect)->toStartWith($redirectUri)->toContain('error=access_denied');
});

it('serves neither MCP nor OAuth routes when disabled', function () {
    config(['pricore.mcp.enabled' => false]);
    Route::setRoutes(new RouteCollection);

    try {
        (new McpServiceProvider(app()))->register();
        require base_path('routes/ai.php');

        expect(Passport::$registersRoutes)->toBeFalse()
            ->and(Route::has('mcp'))->toBeFalse()
            ->and(Route::has('mcp.oauth.register'))->toBeFalse();
    } finally {
        Passport::$registersRoutes = true;
    }
});
