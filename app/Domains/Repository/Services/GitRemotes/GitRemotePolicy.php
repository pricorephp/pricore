<?php

namespace App\Domains\Repository\Services\GitRemotes;

use App\Domains\Mirror\Contracts\Interfaces\HostResolverInterface;
use App\Domains\Repository\Contracts\Interfaces\GitRemoteProbeInterface;
use App\Domains\Repository\Exceptions\UnsafeGitRemoteException;
use App\Domains\Repository\Rules\ValidRepositoryIdentifier;
use App\Services\Network\IpAddressClassifier;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriComparator;
use GuzzleHttp\Psr7\UriResolver;
use Throwable;

class GitRemotePolicy
{
    private const MAX_REDIRECTS = 5;

    public function __construct(
        private readonly HostResolverInterface $hostResolver,
        private readonly GitRemoteProbeInterface $gitRemoteProbe,
    ) {}

    /**
     * Checks the host a generic Git URL names, without connecting to it.
     */
    public function check(string $url): ResolvedGitRemote
    {
        $url = trim($url);

        if (! ValidRepositoryIdentifier::isSafeGitUrl($url) || preg_match('/[\s\\\\\x00-\x1f\x7f]/', $url) === 1) {
            throw new UnsafeGitRemoteException('Refusing to use an unsupported repository URL.');
        }

        if (preg_match('#^([A-Za-z][A-Za-z0-9+.-]*)://([^/?\#]*)(.*)$#', $url, $matches) !== 1) {
            // scp-style shorthand, e.g. git@github.com:owner/repo.git
            preg_match('#^[^@]+@([^:]+):#', $url, $shorthandMatches);
            $host = $this->canonicalHost($shorthandMatches[1] ?? '');

            return new ResolvedGitRemote($url, 'ssh', $host, addresses: $this->addressesFor($host));
        }

        $scheme = strtolower($matches[1]);
        $authority = $matches[2];
        $separator = strrpos($authority, '@');
        $userInfo = $separator === false ? '' : substr($authority, 0, $separator);
        $hostAndPort = $separator === false ? $authority : substr($authority, $separator + 1);

        if (preg_match('#^(\[[0-9A-Fa-f:.]+\]|[^:\[\]]+)(?::(\d{1,5}))?$#', $hostAndPort, $hostMatches) !== 1) {
            throw new UnsafeGitRemoteException('Refusing to use an unsupported repository URL.');
        }

        $host = $this->canonicalHost($hostMatches[1]);
        $port = isset($hostMatches[2]) ? (int) $hostMatches[2] : null;
        $addresses = $this->addressesFor($host);

        // git:// has no option to hold the connection to an address, so the address replaces the name
        $urlHost = $scheme === 'git' && $addresses !== []
            ? ResolvedGitRemote::preferredAddress($addresses)
            : $host;

        // Rebuilt from its parts, so git and its transports cannot read another host out of it
        $rebuiltUrl = "{$scheme}://"
            .($userInfo === '' ? '' : $this->encodeUserInfo($userInfo).'@')
            .(str_contains($urlHost, ':') ? "[{$urlHost}]" : $urlHost)
            .($port === null ? '' : ":{$port}")
            .$matches[3];

        return new ResolvedGitRemote($rebuiltUrl, $scheme, $host, $port, $addresses);
    }

    /**
     * Checks the URL and, over HTTP, every host it redirects to. Returns the
     * remote git can be pointed at with redirects turned off.
     */
    public function resolve(string $url): ResolvedGitRemote
    {
        $gitRemote = $this->check($url);

        if (! $gitRemote->isHttp()) {
            return $gitRemote;
        }

        for ($redirects = 0; ; $redirects++) {
            $location = $this->gitRemoteProbe->redirectLocation($gitRemote);

            if ($location === null) {
                return $gitRemote;
            }

            if ($redirects >= self::MAX_REDIRECTS) {
                throw new UnsafeGitRemoteException('The repository URL redirects too many times.');
            }

            $gitRemote = $this->check($this->redirectedUrl($gitRemote, $location));
        }
    }

    /**
     * git only accepts a redirect that keeps the request it made, and takes
     * whatever precedes it as the new repository URL.
     */
    private function redirectedUrl(ResolvedGitRemote $gitRemote, string $location): string
    {
        try {
            $from = new Uri($gitRemote->url);
            $target = UriResolver::resolve(new Uri($gitRemote->refAdvertisementUrl()), new Uri($location))->withUserInfo('');
        } catch (Throwable) {
            throw new UnsafeGitRemoteException('The repository URL redirects to an invalid address.');
        }

        $redirectedUrl = (string) $target;

        if (! in_array($target->getScheme(), ['http', 'https'], true)
            || ! str_ends_with($redirectedUrl, ResolvedGitRemote::REF_ADVERTISEMENT)) {
            throw new UnsafeGitRemoteException('The repository URL redirects to an address that is not a Git repository.');
        }

        $redirectedUrl = substr($redirectedUrl, 0, -strlen(ResolvedGitRemote::REF_ADVERTISEMENT));

        // Credentials stay with the origin they were given for
        if ($from->getUserInfo() === '' || UriComparator::isCrossOrigin($from, $target)) {
            return $redirectedUrl;
        }

        $schemeLength = strlen($target->getScheme()) + 3;

        return substr($redirectedUrl, 0, $schemeLength).$from->getUserInfo().'@'.substr($redirectedUrl, $schemeLength);
    }

    /**
     * The addresses to hold the connection to. Empty for an IP address, which
     * needs no resolving, and for a host that is allowed on a private network.
     *
     * @return array<int, string>
     */
    private function addressesFor(string $host): array
    {
        if ($this->isAllowedPrivateHost($host)) {
            return [];
        }

        $addresses = [];

        foreach ($this->hostResolver->resolve($host) as $address) {
            $address = IpAddressClassifier::canonical($address);

            if ($address === null || ! IpAddressClassifier::isPublic($address)) {
                $addresses = [];

                break;
            }

            $addresses[] = $address;
        }

        if ($addresses === []) {
            throw new UnsafeGitRemoteException(
                "Repository host {$host} is not reachable on a public address. A host on a private network has to be listed in GIT_ALLOWED_PRIVATE_HOSTS."
            );
        }

        return IpAddressClassifier::canonical($host) === null ? array_values(array_unique($addresses)) : [];
    }

    private function canonicalHost(string $host): string
    {
        $host = strtolower(rtrim(trim($host, '[]'), '.'));
        $address = IpAddressClassifier::canonical($host);

        if ($address !== null) {
            return $address;
        }

        $labels = explode('.', $host);

        // A name ending in a number is an IPv4 address in one of its other notations (127.1, 0x7f.1, 2130706433)
        if (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || preg_match('/^(\d+|0x[0-9a-f]*)$/', end($labels)) === 1) {
            throw new UnsafeGitRemoteException('The repository URL contains an invalid hostname.');
        }

        return $host;
    }

    private function encodeUserInfo(string $userInfo): string
    {
        return implode(':', array_map(
            static fn (string $part): string => rawurlencode(rawurldecode($part)),
            explode(':', $userInfo, 2),
        ));
    }

    private function isAllowedPrivateHost(string $host): bool
    {
        $allowedHosts = config('pricore.git.allowed_private_hosts', []);

        if (! is_array($allowedHosts)) {
            return false;
        }

        foreach ($allowedHosts as $allowedHost) {
            if (! is_string($allowedHost)) {
                continue;
            }

            $allowedHost = strtolower(rtrim(trim($allowedHost, " \t\n\r\0\x0B[]"), '.'));

            if ((IpAddressClassifier::canonical($allowedHost) ?? $allowedHost) === $host) {
                return true;
            }
        }

        return false;
    }
}
