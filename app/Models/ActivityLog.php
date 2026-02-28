<?php

namespace App\Models;

use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Models\Concerns\HasUuids;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string $organization_uuid
 * @property string|null $actor_uuid
 * @property ActivityType $type
 * @property string|null $subject_type
 * @property string|null $subject_uuid
 * @property array<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property-read Organization $organization
 * @property-read User|null $actor
 * @property-read Model|null $subject
 *
 * @method static ActivityLogFactory factory($count = null, $state = [])
 * @method static Builder<static>|ActivityLog newModelQuery()
 * @method static Builder<static>|ActivityLog newQuery()
 * @method static Builder<static>|ActivityLog query()
 *
 * @mixin \Eloquent
 */
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory, HasUuids;

    const UPDATED_AT = null;

    protected $guarded = ['uuid'];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'properties' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_uuid', 'uuid');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_uuid', 'uuid');
    }
}
