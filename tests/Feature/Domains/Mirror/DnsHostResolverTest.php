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

it('follows CNAME records and returns every address', function () {
    $resolver = fakeDnsHostResolver([
        'registry.example.com' => [DNS_A | DNS_CNAME => [['target' => 'edge.example.net']], DNS_AAAA => []],
        'edge.example.net' => [
            DNS_A | DNS_CNAME => [['ip' => '93.184.216.34']],
            DNS_AAAA => [['ipv6' => '2606:4700:4700::1111']],
        ],
    ]);

    expect($resolver->resolve('registry.example.com'))->toBe(['93.184.216.34', '2606:4700:4700::1111']);
});

it('keeps IPv4 addresses when the IPv6 lookup fails', function () {
    $resolver = fakeDnsHostResolver([
        'codeload.example.com' => [
            DNS_A | DNS_CNAME => [['ip' => '140.82.121.9']],
            DNS_AAAA => false,
        ],
    ]);

    expect($resolver->resolve('codeload.example.com'))->toBe(['140.82.121.9']);
});

it('keeps IPv6 addresses when the IPv4 lookup fails', function () {
    $resolver = fakeDnsHostResolver([
        'registry.example.com' => [
            DNS_A | DNS_CNAME => false,
            DNS_AAAA => [['ipv6' => '2606:4700:4700::1111']],
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
