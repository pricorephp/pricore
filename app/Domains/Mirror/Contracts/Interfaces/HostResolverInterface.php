<?php

namespace App\Domains\Mirror\Contracts\Interfaces;

interface HostResolverInterface
{
    /**
     * Resolve every address advertised for a hostname, including CNAME targets.
     *
     * @return array<int, string>
     */
    public function resolve(string $host): array;
}
