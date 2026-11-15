<?php

use App\Domains\Mirror\Services\Http\DnsHostResolver;

/**
 * @param  array<string, array<int, array<int, array<string, mixed>>|false>>  $records  host => [type => records]
 */
function fakeDnsHostResolver(array $records): DnsHostResolver
{
    return new class($records) extends DnsHostResolver
    {
        /**
         * @param  array<string, array<int, array<int, array<string, mixed>>|false>>  $records
         */
        public function __construct(private array $records) {}

        protected function lookup(string $host, int $type): array|false
        {
            return $this->records[$host][$type] ?? false;
        }
    };
}

it('follows CNAME records to their IPv4 addresses', function () {
    $resolver = fakeDnsHostResolver([
        'registry.example.com' => [DNS_A | DNS_CNAME => [['target' => 'edge.example.net']]],
        'edge.example.net' => [DNS_A | DNS_CNAME => [['ip' => '93.184.216.34'], ['ip' => '93.184.216.35']]],
    ]);

    expect($resolver->resolve('registry.example.com'))->toBe(['93.184.216.34', '93.184.216.35']);
});

it('keeps IPv4 addresses when the IPv6 lookup fails', function () {
    $resolver = fakeDnsHostResolver([
        'codeload.example.com' => [
            DNS_A | DNS_CNAME => [['ip' => '140.82.121.9']],
            DNS_AAAA | DNS_CNAME => false,
        ],
    ]);

    expect($resolver->resolve('codeload.example.com'))->toBe(['140.82.121.9']);
});

it('falls back to IPv6 for hosts without an IPv4 address', function () {
    $resolver = fakeDnsHostResolver([
        'registry.example.com' => [
            DNS_A | DNS_CNAME => [],
            DNS_AAAA | DNS_CNAME => [['target' => 'edge.example.net']],
        ],
        'edge.example.net' => [
            DNS_A | DNS_CNAME => [],
            DNS_AAAA | DNS_CNAME => [['ipv6' => '2606:4700:4700::1111']],
        ],
    ]);

    expect($resolver->resolve('registry.example.com'))->toBe(['2606:4700:4700::1111']);
});

it('stops CNAME cycles', function () {
    $resolver = new class extends DnsHostResolver
    {
        protected function lookup(string $host, int $type): array|false
        {
            return [['target' => $host]];
        }
    };

    expect($resolver->resolve('registry.example.com'))->toBe([]);
});
