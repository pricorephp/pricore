<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;

class RefData extends Data
{
    public function __construct(
        public string $name,
        public string $commit,
        public bool $isTag = false,
        /**
         * For a shared tag: the packages that release the same version from a tag
         * of their own, which outranks this one, mapped to that tag's name.
         *
         * @var array<string, string>
         */
        public array $packageTags = [],
    ) {}
}
