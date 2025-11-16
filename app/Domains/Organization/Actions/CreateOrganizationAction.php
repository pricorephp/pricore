<?php

namespace App\Domains\Organization\Actions;

use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Models\Organization;
use Illuminate\Support\Str;

class CreateOrganizationAction
{
    public function handle(string $name, string $ownerUuid): OrganizationData
    {
        $slug = $this->generateUniqueSlug($name);

        $organization = Organization::create([
            'name' => $name,
            'slug' => $slug,
            'owner_uuid' => $ownerUuid,
        ]);

        // Add creator as owner in organization_users pivot
        $organization->members()->attach($ownerUuid, [
            'uuid' => Str::uuid()->toString(),
            'role' => 'owner',
        ]);

        return OrganizationData::fromModel($organization);
    }

    protected function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
