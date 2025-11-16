<?php

namespace Database\Seeders;

use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test user
        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        // Create additional users
        $users = User::factory(8)->create();
        $allUsers = collect([$testUser])->merge($users);

        // Create organizations with different owners
        $organizations = collect();

        // Organization 1: Owned by test user

        /** @var Organization $org1 */
        $org1 = Organization::firstOrCreate(
            ['slug' => 'acme-corp-1234'],
            [
                'name' => 'Acme Corporation',
                'owner_uuid' => $testUser->uuid,
            ]
        );
        $organizations->push($org1);

        // Add members to organization 1
        OrganizationUser::firstOrCreate(
            ['organization_uuid' => $org1->uuid, 'user_uuid' => $testUser->uuid],
            ['role' => 'owner']
        );
        OrganizationUser::firstOrCreate(
            ['organization_uuid' => $org1->uuid, 'user_uuid' => $users[0]?->uuid],
            ['role' => 'admin']
        );
        OrganizationUser::firstOrCreate(
            ['organization_uuid' => $org1->uuid, 'user_uuid' => $users[1]?->uuid],
            ['role' => 'member']
        );
        OrganizationUser::firstOrCreate(
            ['organization_uuid' => $org1->uuid, 'user_uuid' => $users[2]?->uuid],
            ['role' => 'member']
        );

        // Organization 2-4: Create additional organizations
        /** @var Organization $org2 */
        $org2 = Organization::factory()
            ->ownedBy($users[3])
            ->create(['name' => 'Tech Innovators', 'slug' => 'tech-innovators-5678']);

        $organizations->push($org2);

        OrganizationUser::firstOrCreate(
            ['organization_uuid' => $org2->uuid, 'user_uuid' => $users[3]?->uuid],
            ['role' => 'owner']
        );
        OrganizationUser::firstOrCreate(
            ['organization_uuid' => $org2->uuid, 'user_uuid' => $users[4]?->uuid],
            ['role' => 'admin']
        );
        OrganizationUser::firstOrCreate(
            ['organization_uuid' => $org2->uuid, 'user_uuid' => $testUser->uuid],
            ['role' => 'member']
        );

        $org3 = Organization::factory()
            ->ownedBy($users[5])
            ->create(['name' => 'Digital Solutions', 'slug' => 'digital-solutions-9012']);
        $organizations->push($org3);

        OrganizationUser::firstOrCreate(
            ['organization_uuid' => $org3->uuid, 'user_uuid' => $users[5]?->uuid],
            ['role' => 'owner']
        );
        OrganizationUser::firstOrCreate(
            ['organization_uuid' => $org3->uuid, 'user_uuid' => $users[6]?->uuid],
            ['role' => 'member']
        );

        // Create repositories for each organization
        $repositories = collect();

        // Repositories for Acme Corporation
        $repo1 = Repository::factory()
            ->forOrganization($org1)
            ->github()
            ->synced()
            ->create(['name' => 'acme-core', 'repo_identifier' => 'acme-corp/core']);
        $repositories->push($repo1);

        $repo2 = Repository::factory()
            ->forOrganization($org1)
            ->gitlab()
            ->create(['name' => 'acme-api', 'repo_identifier' => 'acme-corp/api']);
        $repositories->push($repo2);

        // Repositories for Tech Innovators
        $repo3 = Repository::factory()
            ->forOrganization($org2)
            ->github()
            ->synced()
            ->create(['name' => 'innovate-framework', 'repo_identifier' => 'tech-innovators/framework']);
        $repositories->push($repo3);

        $repo4 = Repository::factory()
            ->forOrganization($org2)
            ->create(['name' => 'innovate-tools', 'provider' => 'bitbucket', 'repo_identifier' => 'tech-innovators/tools']);
        $repositories->push($repo4);

        // Repositories for Digital Solutions
        $repo5 = Repository::factory()
            ->forOrganization($org3)
            ->github()
            ->create(['name' => 'digital-sdk', 'repo_identifier' => 'digital-solutions/sdk']);
        $repositories->push($repo5);

        // Create packages linked to repositories
        $packages = collect();

        // Packages for Acme Corporation
        $package1 = Package::factory()
            ->forOrganization($org1)
            ->forRepository($repo1)
            ->create(['name' => 'acme/core-framework', 'description' => 'Core framework for Acme applications', 'type' => 'framework']);
        $packages->push($package1);

        $package2 = Package::factory()
            ->forOrganization($org1)
            ->forRepository($repo1)
            ->create(['name' => 'acme/utilities', 'description' => 'Utility library for Acme projects', 'type' => 'library']);
        $packages->push($package2);

        $package3 = Package::factory()
            ->forOrganization($org1)
            ->forRepository($repo2)
            ->create(['name' => 'acme/api-client', 'description' => 'API client for Acme services', 'type' => 'library']);
        $packages->push($package3);

        // Packages for Tech Innovators
        $package4 = Package::factory()
            ->forOrganization($org2)
            ->forRepository($repo3)
            ->create(['name' => 'innovate/framework', 'description' => 'Modern PHP framework', 'type' => 'framework']);
        $packages->push($package4);

        $package5 = Package::factory()
            ->forOrganization($org2)
            ->forRepository($repo3)
            ->create(['name' => 'innovate/http-client', 'description' => 'HTTP client library', 'type' => 'library']);
        $packages->push($package5);

        $package6 = Package::factory()
            ->forOrganization($org2)
            ->forRepository($repo4)
            ->create(['name' => 'innovate/dev-tools', 'description' => 'Development tools', 'type' => 'tool']);
        $packages->push($package6);

        // Packages for Digital Solutions
        $package7 = Package::factory()
            ->forOrganization($org3)
            ->forRepository($repo5)
            ->create(['name' => 'digital/sdk', 'description' => 'Digital Solutions SDK', 'type' => 'library']);
        $packages->push($package7);

        // Some packages without repositories
        $package8 = Package::factory()
            ->forOrganization($org1)
            ->withoutRepository()
            ->create(['name' => 'acme/internal-package', 'description' => 'Internal use only', 'type' => 'library']);
        $packages->push($package8);

        // Create package versions for each package
        foreach ($packages as $package) {
            // Create 3-5 versions per package
            $versionCount = fake()->numberBetween(3, 5);

            for ($i = 0; $i < $versionCount; $i++) {
                PackageVersion::factory()
                    ->forPackage($package)
                    ->create([
                        'composer_json' => [
                            'name' => $package->name,
                            'description' => $package->description,
                            'version' => "{$i}.0.0",
                            'type' => $package->type,
                            'license' => 'MIT',
                            'authors' => [
                                [
                                    'name' => fake()->name(),
                                    'email' => fake()->safeEmail(),
                                ],
                            ],
                            'require' => [
                                'php' => '^8.1 || ^8.2 || ^8.3',
                            ],
                            'autoload' => [
                                'psr-4' => [
                                    'Vendor\\Package\\' => 'src/',
                                ],
                            ],
                        ],
                    ]);
            }

            // Add a dev version for some packages
            if (fake()->boolean(60)) {
                PackageVersion::factory()
                    ->forPackage($package)
                    ->devBranch('main')
                    ->create();
            }

            // Add a beta version for some packages
            if (fake()->boolean(40)) {
                PackageVersion::factory()
                    ->forPackage($package)
                    ->beta()
                    ->create();
            }
        }

        // Create access tokens
        // Organization tokens
        AccessToken::factory()
            ->forOrganization($org1)
            ->withScopes(['read', 'write'])
            ->recentlyUsed()
            ->create(['name' => 'CI/CD Token']);

        AccessToken::factory()
            ->forOrganization($org1)
            ->withScopes(['read'])
            ->create(['name' => 'Read-only Token']);

        AccessToken::factory()
            ->forOrganization($org2)
            ->withScopes(['read', 'write', 'admin'])
            ->create(['name' => 'Admin Token']);

        AccessToken::factory()
            ->forOrganization($org3)
            ->withScopes(['read'])
            ->neverExpires()
            ->create(['name' => 'Production Token']);

        // User tokens
        AccessToken::factory()
            ->forUser($testUser)
            ->withScopes(['read', 'write'])
            ->recentlyUsed()
            ->create(['name' => 'Personal Token']);

        AccessToken::factory()
            ->forUser($users[0])
            ->withScopes(['read'])
            ->create(['name' => 'Dev Token']);
    }
}
