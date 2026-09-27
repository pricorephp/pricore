<?php

namespace App\Domains\Package\Contracts\Enums;

enum ArtifactPublishResult: string
{
    case Added = 'added';
    case Replaced = 'replaced';
    case Unchanged = 'unchanged';

    public function isUnchanged(): bool
    {
        return $this === self::Unchanged;
    }
}
