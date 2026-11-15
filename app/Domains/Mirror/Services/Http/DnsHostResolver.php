<?php

namespace App\Domains\Mirror\Services\Http;

use App\Domains\Mirror\Contracts\Interfaces\HostResolverInterface;

class DnsHostResolver implements HostResolverInterface
{
    private const MAX_CNAME_DEPTH = 8;

    public function resolve(string $host): array
    {
        $literal = trim($host, '[]');

        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            return [$literal];
        }

        return array_values(array_unique($this->resolveHostname($host, [], 0)));
    }

    /**
     * IPv4 is looked up on its own, and IPv6 only when a host has no IPv4
     * address. Asking for every record type at once fails as a whole when one
     * of them times out, which discarded working IPv4 answers.
     *
     * @param  array<string, true>  $visited
     * @return array<int, string>
     */
    private function resolveHostname(string $host, array $visited, int $depth): array
    {
        $host = strtolower(rtrim($host, '.'));

        if ($depth >= self::MAX_CNAME_DEPTH || isset($visited[$host])) {
            return [];
        }

        $visited[$host] = true;

        $addresses = $this->resolveRecords($host, DNS_A | DNS_CNAME, $visited, $depth);

        if ($addresses === []) {
            $addresses = $this->resolveRecords($host, DNS_AAAA | DNS_CNAME, $visited, $depth);
        }

        return $addresses;
    }

    /**
     * @param  array<string, true>  $visited
     * @return array<int, string>
     */
    private function resolveRecords(string $host, int $type, array $visited, int $depth): array
    {
        $records = $this->lookup($host, $type);

        if ($records === false) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $addresses[] = $record['ip'];
            }

            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }

            if (isset($record['target']) && is_string($record['target'])) {
                $addresses = array_merge(
                    $addresses,
                    $this->resolveHostname($record['target'], $visited, $depth + 1),
                );
            }
        }

        return $addresses;
    }

    /**
     * @return array<int, array<string, mixed>>|false
     */
    protected function lookup(string $host, int $type): array|false
    {
        // Fully qualified first: with a search domain configured, a name without
        // one of the record types is otherwise retried under every search domain.
        $records = @dns_get_record("{$host}.", $type);

        if ($records === false || $records === []) {
            $records = @dns_get_record($host, $type);
        }

        return $records;
    }
}
