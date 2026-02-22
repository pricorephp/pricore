<?php

namespace App\Domains\Composer\Contracts\Data;

use Spatie\LaravelData\Data;

class DownloadNotificationData extends Data
{
    public function __construct(
        public string $name,
        public string $version,
    ) {}
}
