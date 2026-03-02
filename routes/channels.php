<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('organization.{organizationUuid}', function (User $user, string $organizationUuid) {
    return $user->organizations()->where('organizations.uuid', $organizationUuid)->exists();
});
