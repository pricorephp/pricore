<?php

namespace App\Domains\Activity\Actions;

use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordActivityTask
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        Organization $organization,
        ActivityType $type,
        ?Model $subject = null,
        ?User $actor = null,
        array $properties = [],
    ): ActivityLog {
        return ActivityLog::create([
            'organization_uuid' => $organization->uuid,
            'actor_uuid' => $actor?->uuid,
            'type' => $type,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_uuid' => $subject?->getKey(),
            'properties' => $properties ?: null,
        ]);
    }
}
