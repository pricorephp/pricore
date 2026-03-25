<?php

namespace App\Domains\Security\Contracts\Data;

use App\Models\SecurityAdvisory;
use Spatie\LaravelData\Data;

class ComposerAdvisoryData extends Data
{
    /**
     * @param  array<int, array{name: string, remoteId: string}>  $sources
     */
    public function __construct(
        public string $advisoryId,
        public string $packageName,
        public string $title,
        public ?string $link,
        public ?string $cve,
        public string $affectedVersions,
        public array $sources,
        public ?string $reportedAt,
        public ?string $composerRepository,
        public string $severity,
    ) {}

    public static function fromModel(SecurityAdvisory $advisory): self
    {
        return new self(
            advisoryId: $advisory->advisory_id,
            packageName: $advisory->package_name,
            title: $advisory->title,
            link: $advisory->link,
            cve: $advisory->cve,
            affectedVersions: $advisory->affected_versions,
            sources: $advisory->sources ?? [],
            reportedAt: $advisory->reported_at?->toIso8601String(),
            composerRepository: $advisory->composer_repository,
            severity: $advisory->severity->value,
        );
    }
}
