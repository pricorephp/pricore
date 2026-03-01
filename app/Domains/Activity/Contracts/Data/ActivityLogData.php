<?php

namespace App\Domains\Activity\Contracts\Data;

use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Models\ActivityLog;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ActivityLogData extends Data
{
    /**
     * @param  array<string, mixed>|null  $properties
     */
    public function __construct(
        public string $uuid,
        public ActivityType $type,
        public string $typeLabel,
        public string $icon,
        public string $category,
        public ?string $actorName,
        public ?string $actorAvatar,
        public ?string $subjectType,
        public ?string $subjectUuid,
        public ?array $properties,
        public ?CarbonInterface $createdAt,
    ) {}

    public static function fromModel(ActivityLog $log): self
    {
        return new self(
            uuid: $log->uuid,
            type: $log->type,
            typeLabel: $log->type->label(),
            icon: $log->type->icon(),
            category: $log->type->category(),
            actorName: $log->actor?->name,
            actorAvatar: $log->actor?->avatar,
            subjectType: $log->subject_type,
            subjectUuid: $log->subject_uuid,
            properties: $log->properties,
            createdAt: $log->created_at,
        );
    }
}
