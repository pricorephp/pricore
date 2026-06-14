<?php

namespace App\Domains\Token\Services;

use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;

class TokenScopeChecker
{
    /**
     * Determine whether the token carries the given scope.
     *
     * A null `scopes` value means the token predates scope support and is
     * treated as having full access (backward compatible). An explicit empty
     * array grants nothing.
     */
    public function hasScope(AccessToken $token, TokenScope $scope): bool
    {
        if ($token->scopes === null) {
            return true;
        }

        return in_array($scope->value, $token->scopes, true);
    }

    /**
     * Determine whether the token carries any of the given scopes.
     */
    public function hasAny(AccessToken $token, TokenScope ...$scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($this->hasScope($token, $scope)) {
                return true;
            }
        }

        return false;
    }
}
