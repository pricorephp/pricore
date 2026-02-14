<?php

namespace App\Models;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\Concerns\HasUuids;
use Database\Factories\UserGitCredentialFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $user_uuid
 * @property GitProvider $provider
 * @property array<string, mixed> $credentials
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
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
