<?php

namespace App\Domains\Token\Commands;

use App\Domains\Token\Actions\CreateAccessTokenAction;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class CreateTokenCommand extends Command
{
    protected $signature = 'token:create
                            {--organization= : Organization UUID or slug}
                            {--user= : User UUID (for user-scoped token)}
                            {--name= : Token name}
                            {--expires= : Expiration period (never, 30d, 90d, 1y, custom)}';

    protected $description = 'Create a new access token for Composer authentication';

    public function __construct(
        protected CreateAccessTokenAction $createAccessToken
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        info('Create Access Token');

        $tokenType = select(
            label: 'Token type',
            options: [
                'organization' => 'Organization-scoped (access one organization)',
                'user' => 'User-scoped (access all user\'s organizations)',
            ],
            default: 'organization'
        );

        $organization = null;
        $user = null;

        if ($tokenType === 'organization') {
            $organization = $this->selectOrganization();
            if (! $organization) {
                return self::FAILURE;
            }
        } else {
            $user = $this->selectUser();
            if (! $user) {
                return self::FAILURE;
            }
        }

        $name = $this->option('name') ?? text(
            label: 'Token name',
            placeholder: 'Production Server',
            required: true
        );

        $expiresAt = $this->getExpiration();

        $result = $this->createAccessToken->handle(
            organization: $organization,
            user: $user,
            name: $name,
            expiresAt: $expiresAt
        );

        $this->newLine();
        $this->components->success('Access token created successfully!');
        $this->newLine();

        warning('This token will only be shown once. Store it securely!');
        $this->newLine();

        $this->components->twoColumnDetail('Token', $result->plainToken);
        $this->components->twoColumnDetail('Name', $result->name);
        $this->components->twoColumnDetail(
            'Type',
            $result->organizationUuid ? 'Organization-scoped' : 'User-scoped'
        );
        $this->components->twoColumnDetail(
            'Expires',
            $result->expiresAt?->format('Y-m-d H:i:s') ?? 'Never'
        );

        $this->newLine();
        note('Configure Composer to use this token:');
        $this->newLine();

        if ($organization) {
            $domain = config('app.url');
            $domain = str_replace(['http://', 'https://'], '', $domain);

            $this->line("  composer config --global --auth http-basic.{$domain} {$result->plainToken} ''");
            $this->newLine();
            $this->line('  Or using Bearer authentication:');
            $this->line("  composer config --global --auth bearer.{$domain} {$result->plainToken}");
        }

        $this->newLine();

        return self::SUCCESS;
    }

    protected function selectOrganization(): ?Organization
    {
        if ($identifier = $this->option('organization')) {
            $organization = Organization::query()
                ->where('uuid', $identifier)
                ->orWhere('slug', $identifier)
                ->first();

            if (! $organization) {
                $this->error("Organization '{$identifier}' not found.");

                return null;
            }

            return $organization;
        }

        $organizationId = search(
            label: 'Select organization',
            options: fn (string $value) => strlen($value) > 0
                ? Organization::query()
                    ->where('name', 'like', "%{$value}%")
                    ->orWhere('slug', 'like', "%{$value}%")
                    ->limit(10)
                    ->get()
                    ->mapWithKeys(fn (Organization $org) => [$org->uuid => "{$org->name} ({$org->slug})"])
                    ->all()
                : [],
            placeholder: 'Search by name or slug...',
        );

        return Organization::find($organizationId);
    }

    protected function selectUser(): ?User
    {
        if ($identifier = $this->option('user')) {
            $user = User::find($identifier);

            if (! $user) {
                $this->error("User '{$identifier}' not found.");

                return null;
            }

            return $user;
        }

        $userId = search(
            label: 'Select user',
            options: fn (string $value) => strlen($value) > 0
                ? User::query()
                    ->where('name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->limit(10)
                    ->get()
                    ->mapWithKeys(fn (User $user) => [$user->uuid => "{$user->name} ({$user->email})"])
                    ->all()
                : [],
            placeholder: 'Search by name or email...',
        );

        return User::find($userId);
    }

    protected function getExpiration(): ?Carbon
    {
        $expires = $this->option('expires') ?? select(
            label: 'Token expiration',
            options: [
                'never' => 'Never expires',
                '30d' => '30 days',
                '90d' => '90 days',
                '1y' => '1 year',
            ],
            default: 'never'
        );

        return match ($expires) {
            'never' => null,
            '30d' => now()->addDays(30),
            '90d' => now()->addDays(90),
            '1y' => now()->addYear(),
            default => null,
        };
    }
}
