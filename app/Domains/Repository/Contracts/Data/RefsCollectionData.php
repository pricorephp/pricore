<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class RefsCollectionData extends Data
{
    public function __construct(
        /** @var DataCollection<int, RefData> */
        #[DataCollectionOf(RefData::class)]
        public DataCollection $tags,

        /** @var DataCollection<int, RefData> */
        #[DataCollectionOf(RefData::class)]
        public DataCollection $branches,

        /** @var DataCollection<int, RefData> */
        #[DataCollectionOf(RefData::class)]
        public DataCollection $all,
    ) {}
}
