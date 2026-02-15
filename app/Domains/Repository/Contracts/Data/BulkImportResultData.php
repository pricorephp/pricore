<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;

class BulkImportResultData extends Data
{
    public function __construct(
        public int $created,
        public int $skipped,
        public int $webhooksFailed,
    ) {}

    public function statusMessage(): string
    {
        $parts = [];

        if ($this->created > 0) {
            $parts[] = $this->created.' '.($this->created === 1 ? 'repository' : 'repositories').' imported';
        }

        if ($this->skipped > 0) {
            $parts[] = $this->skipped.' already connected';
        }

        if ($this->webhooksFailed > 0) {
            $parts[] = $this->webhooksFailed.' webhook '.($this->webhooksFailed === 1 ? 'registration' : 'registrations').' failed';
        }

        if (empty($parts)) {
            return 'No repositories were imported.';
        }

        return implode(', ', $parts).'.';
    }
}
