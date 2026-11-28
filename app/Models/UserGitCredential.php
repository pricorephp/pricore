<?php

namespace App\Models;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\Concerns\HasUuids;
use Database\Factories\UserGitCredentialFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $user_uuid
 * @property GitProvider $provider
 * @property string|null $name
 * @property string|null $account_id
 * @property array<string, mixed> $credentials
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Repository> $repositories
 * @property-read int|null $repositories_count
 *
 * @method static UserGitCredentialFactory factory($count = null, $state = [])
 * @method static Builder<static>|UserGitCredential newModelQuery()
 * @method static Builder<static>|UserGitCredential newQuery()
 * @method static Builder<static>|UserGitCredential query()
 *
 * @mixin Eloquent
 */
class UserGitCredential extends Model
{
    /** @use HasFactory<UserGitCredentialFactory> */
    use HasFactory;

    use HasUuids;

    protected $guarded = ['uuid'];

    protected $casts = [
        'provider' => GitProvider::class,
    ];

    protected static function booted(): void
    {
        // Repositories of a removed credential must not fall back to another account of the same user
        static::deleting(function (UserGitCredential $credential): void {
            $credential->repositories()->update(['credential_user_uuid' => null]);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * @return HasMany<Repository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class, 'git_credential_uuid', 'uuid');
    }

    /**
     * Instance URL for self-hosted capable providers, defaulting to the public host.
     */
    public function baseUrl(): ?string
    {
        if (! $this->provider->supportsSelfHosted()) {
            return null;
        }

        $url = $this->credentials['url'] ?? null;

        return is_string($url) && $url !== '' ? rtrim($url, '/') : null;
    }

    public function label(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        return match ($this->provider) {
            GitProvider::GitLab => parse_url($this->baseUrl() ?? 'https://gitlab.com', PHP_URL_HOST) ?: 'GitLab',
            GitProvider::Bitbucket => $this->credentials['email'] ?? $this->provider->label(),
            default => $this->provider->label(),
        };
    }

    /**
     * Whether this credential authenticates against the host the repository lives on.
     */
    public function servesBaseUrl(?string $baseUrl): bool
    {
        return $this->provider->isSameInstance($this->baseUrl(), $baseUrl);
    }

    /**
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? json_decode(decrypt($value), true) : [],
            set: fn (array $value) => encrypt(json_encode($value)),
        );
    }
}
