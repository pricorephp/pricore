<?php

namespace App\Domains\Mirror\Services\Http;

readonly class ResolvedMirrorUrl
{
    /**
     * @param  array<int, string>  $addresses
     */
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public array $addresses,
        public bool $literalHost,
    ) {}

    /**
     * Pin the validated DNS result so the HTTP connection cannot resolve the
     * hostname to a different address after policy evaluation.
     *
     * @return array<int, string>
     */
    public function curlResolveEntries(): array
    {
        if ($this->literalHost) {
            return [];
        }

        $addresses = array_map(
            static fn (string $address): string => str_contains($address, ':') ? "[{$address}]" : $address,
            $this->addresses,
        );

        return ["{$this->host}:{$this->port}:".implode(',', $addresses)];
    }
}
