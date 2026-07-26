<?php

namespace App\Domains\Repository\Support;

/**
 * Git echoes the remote URL in its error output. When credentials are carried in
 * that URL they end up in repository_sync_logs.error_message, on the repository
 * page, and in Sentry — so strip them before the output goes anywhere.
 */
final class GitCredentialScrubber
{
    public static function scrub(string $output): string
    {
        // scheme://user:secret@host  ->  scheme://***@host
        return preg_replace('#(?<=://)[^/\s@]+@#', '***@', $output) ?? $output;
    }
}
