<?php

namespace App\Http\Data;

use App\Domains\Search\Contracts\Data\SearchPackageData;
use App\Domains\Search\Contracts\Data\SearchRepositoryData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SearchData extends Data
{
    /**
     * @param  array<int, SearchPackageData>  $packages
     * @param  array<int, SearchRepositoryData>  $repositories
     */
    public function __construct(
        public array $packages,
        public array $repositories,
    ) {}
}
