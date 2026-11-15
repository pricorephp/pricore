<?php

namespace App\Domains\Package\Contracts\Data;

use App\Domains\Package\Contracts\Enums\PackageSource;
use App\Domains\Package\Contracts\Enums\PackageStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PackageFiltersData extends Data
{
    public function __construct(
        public string $query = '',
        public ?PackageSource $source = null,
        public ?string $mirror = null,
        public ?PackageStatus $status = null,
    ) {}

    /**
     * Unknown values are dropped rather than rejected, so a stale or
     * hand-edited link still shows the list instead of an error.
     */
    public static function fromRequest(Request $request): self
    {
        $mirror = $request->string('mirror')->trim()->value();
        $mirror = Str::isUuid($mirror) ? $mirror : null;

        return new self(
            query: Str::limit($request->string('query')->trim()->value(), 100, ''),
            source: $mirror !== null
                ? PackageSource::Mirror
                : PackageSource::tryFrom($request->string('source')->value()),
            mirror: $mirror,
            status: PackageStatus::tryFrom($request->string('status')->value()),
        );
    }

    public function isActive(): bool
    {
        return $this->query !== '' || $this->source !== null || $this->status !== null;
    }
}
