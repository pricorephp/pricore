<?php

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\UserGitCredential;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaultUrl = rtrim((string) config('services.gitlab.instance_uri'), '/');

        if ($defaultUrl === '') {
            return;
        }

        UserGitCredential::query()
            ->where('provider', GitProvider::GitLab)
            ->get()
            ->each(function (UserGitCredential $credential) use ($defaultUrl): void {
                if (! empty($credential->credentials['url'] ?? null)) {
                    return;
                }

                $credential->credentials = [
                    ...$credential->credentials,
                    'url' => $defaultUrl,
                ];

                $credential->save();
            });
    }

    public function down(): void
    {
        // Not reversible — we can't distinguish backfilled URLs from user-set ones.
    }
};
