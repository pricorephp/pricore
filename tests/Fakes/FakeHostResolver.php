<?php

namespace Tests\Fakes;

use App\Domains\Mirror\Contracts\Interfaces\HostResolverInterface;

class FakeHostResolver implements HostResolverInterface
{
    /** @var array<string, array<int, string>> */
    private array $addresses = [];

    /** @var array<string, array<int, array<int, string>>> */
    private array $sequences = [];

    /** @var array<int, string> */
    public array $resolvedHosts = [];

    /**
     * @param  array<int, string>  $addresses
     */
    public function set(string $host, array $addresses): void
    {
        $this->addresses[strtolower($host)] = $addresses;
    }

    /**
     * @param  array<int, array<int, string>>  $sequence
     */
    public function setSequence(string $host, array $sequence): void
    {
        $this->sequences[strtolower($host)] = $sequence;
    }

    public function resolve(string $host): array
    {
        $host = strtolower($host);
        $this->resolvedHosts[] = $host;

        if (isset($this->sequences[$host]) && $this->sequences[$host] !== []) {
            return array_shift($this->sequences[$host]);
        }

        if (isset($this->addresses[$host])) {
            return $this->addresses[$host];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        return ['93.184.216.34'];
    }
}
