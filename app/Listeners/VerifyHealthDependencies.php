<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Runs on every hit to /up. Throwing here turns the health endpoint red, which is
 * the point: container orchestrators gate dependent services on this, so a check
 * that only proves "PHP answered" is worse than no check at all.
 */
class VerifyHealthDependencies
{
    public function handle(DiagnosingHealth $event): void
    {
        $this->checkDatabase();
        $this->checkCache();
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo()->query('SELECT 1');
        } catch (\Throwable $e) {
            throw new RuntimeException('Database connection failed: '.$e->getMessage(), previous: $e);
        }
    }

    private function checkCache(): void
    {
        try {
            Cache::store()->put('pricore:health', 1, 10);
        } catch (\Throwable $e) {
            throw new RuntimeException('Cache store unreachable: '.$e->getMessage(), previous: $e);
        }
    }
}
