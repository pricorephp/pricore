<?php

namespace App\Domains\Auth\Contracts\Enums;

enum GitHubOAuthIntent: string
{
    case Login = 'login';
    case Connect = 'connect';

    public function isLogin(): bool
    {
        return $this === self::Login;
    }

    public function isConnect(): bool
    {
        return $this === self::Connect;
    }
}
