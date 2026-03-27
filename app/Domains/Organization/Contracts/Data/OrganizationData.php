<?php

namespace App\Domains\Organization\Contracts\Data;

use App\Models\Organization;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OrganizationData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $slug,
        public string $ownerUuid,
        public string $composerRepositoryUrl,
        public ?OrganizationPermissionsData $permissions = null,
        public ?bool $onTrial = null,
        public ?bool $trialExpired = null,
        public bool $securityAuditsEnabled = true,
        public bool $securityNotificationsEnabled = true,
    ) {}

    public static function fromModel(Organization $organization): self
    {
        return new self(
            uuid: $organization->uuid,
            name: $organization->name,
            slug: $organization->slug,
            ownerUuid: $organization->owner_uuid,
            composerRepositoryUrl: url("/{$organization->slug}"),
            securityAuditsEnabled: $organization->security_audits_enabled,
            securityNotificationsEnabled: $organization->security_notifications_enabled,
        );
    }
}
