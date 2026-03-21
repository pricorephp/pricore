<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationSshKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSshKey>
 */
class OrganizationSshKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_uuid' => Organization::factory(),
            'name' => fake()->words(2, true).' Key',
            'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITest'.fake()->sha256().' pricore:test',
            'private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\n".fake()->sha256()."\n-----END OPENSSH PRIVATE KEY-----",
            'fingerprint' => 'SHA256:'.fake()->sha256(),
        ];
    }
}
