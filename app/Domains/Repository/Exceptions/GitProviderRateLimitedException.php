<?php

namespace App\Domains\Repository\Exceptions;

use Throwable;

class GitProviderRateLimitedException extends GitProviderException
{
    public function __construct(
        public readonly int $retryAfter,
        string $message = '',
    ) {
        parent::__construct($message ?: "Git provider rate limit reached, retry in {$retryAfter} seconds");
    }

    /**
     * Providers wrap request failures in exceptions of their own, so the rate
     * limit may sit anywhere in the chain.
     */
    public static function find(Throwable $exception): ?self
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof self) {
                return $current;
            }
        }

        return null;
    }
}
