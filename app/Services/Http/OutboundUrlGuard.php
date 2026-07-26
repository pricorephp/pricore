<?php

namespace App\Services\Http;

use Illuminate\Support\Str;

/**
 * Decides whether a user-supplied URL may be fetched by the server.
 *
 * Repository clones, registry metadata reads and dist downloads all take their
 * target from user input (or from metadata served by a user-nominated registry),
 * so without this they can be aimed at loopback, the private network, or a cloud
 * instance metadata endpoint.
 */
class OutboundUrlGuard
{
    /**
     * Resolutions memoised for the life of the request. A bulk import validates up to
     * 50 URLs at once, usually against the same host. Deliberately not cached across
     * requests: a stale answer here is a rebinding window.
     *
     * @var array<string, array<int, string>>
     */
    protected array $resolved = [];

    public function allows(string $url): bool
    {
        $host = $this->hostFor($url);

        if ($host === null) {
            return false;
        }

        if ($this->isExplicitlyAllowed($host)) {
            return true;
        }

        $addresses = $this->resolve($host);

        if ($addresses === []) {
            // A host that resolves to nothing cannot be used to reach anything, so
            // there is no target to protect. Rejecting here would only turn transient
            // DNS failures into confusing validation errors.
            return true;
        }

        foreach ($addresses as $address) {
            if ($this->isBlocked($address)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extracts the host from either a URL or git's scp-style shorthand.
     */
    public function hostFor(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (str_contains($url, '://')) {
            $host = parse_url($url, PHP_URL_HOST);

            return is_string($host) && $host !== '' ? strtolower(trim($host, '[]')) : null;
        }

        if (preg_match('#^[A-Za-z0-9._-]+@([A-Za-z0-9.-]+):#', $url, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    protected function isExplicitlyAllowed(string $host): bool
    {
        /** @var array<int, string> $allowed */
        $allowed = config('pricore.outbound.allowed_hosts', []);

        foreach ($allowed as $entry) {
            $entry = strtolower(trim($entry));

            if ($entry === '') {
                continue;
            }

            if ($host === $entry || Str::endsWith($host, '.'.$entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if (isset($this->resolved[$host])) {
            return $this->resolved[$host];
        }

        $addresses = [];

        $v4 = gethostbynamel($host);

        if (is_array($v4)) {
            $addresses = $v4;
        }

        $v6 = @dns_get_record($host, DNS_AAAA);

        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return $this->resolved[$host] = array_values(array_unique($addresses));
    }

    protected function isBlocked(string $address): bool
    {
        /** @var array<int, string> $ranges */
        $ranges = config('pricore.outbound.blocked_ranges', []);

        foreach ($ranges as $range) {
            if ($this->inRange($address, $range)) {
                return true;
            }
        }

        return false;
    }

    protected function inRange(string $address, string $range): bool
    {
        if (! str_contains($range, '/')) {
            return $address === $range;
        }

        [$subnet, $bits] = explode('/', $range, 2);

        $addressBinary = @inet_pton($address);
        $subnetBinary = @inet_pton($subnet);

        if ($addressBinary === false || $subnetBinary === false) {
            return false;
        }

        // Mixing address families never matches.
        if (strlen($addressBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bits = (int) $bits;
        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($wholeBytes > 0 && strncmp($addressBinary, $subnetBinary, $wholeBytes) !== 0) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $remainingBits)) - 1) & 0xFF;

        return (ord($addressBinary[$wholeBytes]) & $mask) === (ord($subnetBinary[$wholeBytes]) & $mask);
    }
}
