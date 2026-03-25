<?php

namespace App\Domains\Security\Contracts\Data;

use App\Domains\Security\Contracts\Enums\AdvisorySeverity;
use App\Models\SecurityAdvisory;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SecurityAdvisoryData extends Data
{
    public function __construct(
        public string $uuid,
        public string $advisoryId,
        public string $packageName,
        public string $title,
        public ?string $link,
        public ?string $cve,
        public string $affectedVersions,
        public AdvisorySeverity $severity,
        public ?CarbonInterface $reportedAt,
    ) {}

    public static function fromModel(SecurityAdvisory $advisory): self
    {
        return new self(
            uuid: $advisory->uuid,
            advisoryId: $advisory->advisory_id,
            packageName: $advisory->package_name,
            title: $advisory->title,
            link: $advisory->link,
            cve: $advisory->cve,
            affectedVersions: $advisory->affected_versions,
            severity: $advisory->severity,
            reportedAt: $advisory->reported_at,
        );
    }
}
