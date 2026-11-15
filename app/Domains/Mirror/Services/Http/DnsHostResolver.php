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
     * IPv4 and IPv6 are looked up separately and both kept, so the connection
     * can use either. Asking for every record type at once fails as a whole
     * when one of them times out, which discarded the working answers.
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

        return array_merge(
            $this->resolveRecords($host, DNS_A | DNS_CNAME, $visited, $depth),
            $this->resolveRecords($host, DNS_AAAA, $visited, $depth),
        );
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
