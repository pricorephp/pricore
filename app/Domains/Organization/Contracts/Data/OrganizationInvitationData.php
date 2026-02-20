<?php

namespace App\Domains\Organization\Contracts\Data;

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\OrganizationInvitation;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class OrganizationInvitationData extends Data
{
    public function __construct(
        public string $uuid,
        public string $email,
        public OrganizationRole $role,
        public string $status,
        public ?string $invitedByName,
        public ?CarbonInterface $createdAt,
        public ?CarbonInterface $expiresAt,
    ) {}

    public static function fromModel(OrganizationInvitation $invitation): self
    {
        $status = match (true) {
            $invitation->isAccepted() => 'accepted',
            $invitation->isExpired() => 'expired',
            default => 'pending',
        };

        return new self(
            uuid: $invitation->uuid,
            email: $invitation->email,
            role: $invitation->role,
            status: $status,
            invitedByName: $invitation->invitedBy?->name,
            createdAt: $invitation->created_at,
            expiresAt: $invitation->expires_at,
        );
    }
}
