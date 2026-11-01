<?php

use App\Domains\Mirror\Services\Http\DnsHostResolver;

it('follows CNAME records and returns every address', function () {
    $resolver = new class extends DnsHostResolver
    {
        /**
         * @return array<int, array<string, mixed>>|false
         */
        protected function lookup(string $host): array|false
        {
            return match ($host) {
                'registry.example.com' => [['target' => 'edge.example.net']],
                'edge.example.net' => [
                    ['ip' => '93.184.216.34'],
                    ['ipv6' => '2606:4700:4700::1111'],
                ],
                default => false,
            };
        }
    };

    expect($resolver->resolve('registry.example.com'))->toBe([
        '93.184.216.34',
        '2606:4700:4700::1111',
    ]);
});

it('stops CNAME cycles', function () {
    $resolver = new class extends DnsHostResolver
    {
        protected function lookup(string $host): array|false
        {
            return [['target' => $host]];
        }
    };

    expect($resolver->resolve('registry.example.com'))->toBe([]);
});
