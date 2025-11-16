<?php

namespace App\Domains\Token\Contracts\Data;

use App\Models\AccessToken;
use Spatie\LaravelData\Data;

class TokenCreatedData extends Data
{
    public function __construct(
        public string $plainToken,
        public AccessToken $accessToken,
    ) {}
}
