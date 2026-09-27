<?php

namespace App\Domains\Package\Contracts\Data;

use Spatie\LaravelData\Data;

class ArtifactContentsData extends Data
{
    /**
     * @param  array<string, mixed>  $composerJson
     */
    public function __construct(
        public array $composerJson,
        public ?string $readme,
    ) {}
}
