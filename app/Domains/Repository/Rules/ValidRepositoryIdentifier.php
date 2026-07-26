<?php

namespace App\Domains\Repository\Rules;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Validates the shape of a repository identifier before it can reach the git CLI.
 *
 * API-backed providers take an "owner/repo" slug. The generic Git provider takes a
 * URL that is handed to `git clone` / `git ls-remote` verbatim, so it must be
 * restricted to network transports: git happily executes commands for
 * `ext::<command>`, treats a leading dash as an option, and reads the local
 * filesystem for `file://` and bare paths.
 */
class ValidRepositoryIdentifier implements ValidationRule
{
    /**
     * Transports git may be pointed at. Notably excludes `file` (local disclosure)
     * and the helper transports reachable through `<transport>::<address>`.
     */
    public const ALLOWED_SCHEMES = ['https', 'http', 'ssh', 'git'];

    public function __construct(private readonly ?GitProvider $provider) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('The :attribute must be a non-empty string.');

            return;
        }

        if ($this->provider === null) {
            $fail('The :attribute cannot be validated without a known provider.');

            return;
        }

        if (static::passes($value, $this->provider)) {
            return;
        }

        $fail($this->provider === GitProvider::Git
            ? 'The :attribute must be an https, http, ssh or git URL.'
            : 'The :attribute must be in "owner/repository" format.');
    }

    public static function passes(string $value, GitProvider $provider): bool
    {
        return $provider === GitProvider::Git
            ? static::isSafeGitUrl($value)
            : static::isSafeSlug($value, $provider);
    }

    /**
     * A URL git may be pointed at without handing control of the host to the caller.
     */
    public static function isSafeGitUrl(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || Str::startsWith($value, '-')) {
            return false;
        }

        // `<transport>::<address>` selects a remote helper — `ext::` runs arbitrary commands.
        if (str_contains($value, '::')) {
            return false;
        }

        if (preg_match('#^([A-Za-z][A-Za-z0-9+.-]*)://#', $value, $matches) === 1) {
            if (! in_array(strtolower($matches[1]), static::ALLOWED_SCHEMES, true)) {
                return false;
            }

            $host = parse_url($value, PHP_URL_HOST);

            return is_string($host) && $host !== '';
        }

        // scp-style shorthand, e.g. git@github.com:owner/repo.git
        return preg_match('#^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+:(?!/)\S+$#', $value) === 1;
    }

    /**
     * An "owner/repo" slug for the API-backed providers. GitLab allows subgroup nesting.
     */
    public static function isSafeSlug(string $value, GitProvider $provider): bool
    {
        $value = trim($value);

        $pattern = $provider === GitProvider::GitLab
            ? '#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)+$#'
            : '#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#';

        if (preg_match($pattern, $value) !== 1) {
            return false;
        }

        // Reject traversal segments that the character class would otherwise allow.
        foreach (explode('/', $value) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
