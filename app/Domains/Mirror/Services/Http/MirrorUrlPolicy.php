<?php

namespace App\Domains\Mirror\Services\Http;

use App\Domains\Mirror\Contracts\Interfaces\HostResolverInterface;
use App\Domains\Mirror\Exceptions\UnsafeMirrorUrlException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriComparator;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

class MirrorUrlPolicy
{
    private const BLOCKED_IPV4_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    private const BLOCKED_IPV6_RANGES = [
        '::/96',
        '100::/64',
        '2001::/23',
        '2001:db8::/32',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'fec0::/10',
        'ff00::/8',
    ];

    public function __construct(
        private readonly HostResolverInterface $hostResolver,
    ) {}

    public function resolveMirrorOrigin(string $url): ResolvedMirrorUrl
    {
        $uri = $this->parse($url);

        return $this->resolve($uri, $this->isAllowedPrivateHost($this->canonicalHost($uri)));
    }

    public function resolveForMirror(string $url, string $mirrorUrl): ResolvedMirrorUrl
    {
        $uri = $this->parse($url);
        $mirrorUri = $this->parse($mirrorUrl);
        $allowPrivate = ! UriComparator::isCrossOrigin($mirrorUri, $uri)
            && $this->isAllowedPrivateHost($this->canonicalHost($mirrorUri));

        return $this->resolve($uri, $allowPrivate);
    }

    public function isSameOrigin(string $url, string $mirrorUrl): bool
    {
        return ! UriComparator::isCrossOrigin($this->parse($mirrorUrl), $this->parse($url));
    }

    private function resolve(UriInterface $uri, bool $allowPrivate): ResolvedMirrorUrl
    {
        $host = $this->canonicalHost($uri);
        $literalHost = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $addresses = $this->hostResolver->resolve($host);

        if ($addresses === []) {
            throw new UnsafeMirrorUrlException("Mirror URL host {$host} could not be resolved.");
        }

        $addresses = array_values(array_unique(array_map(
            fn (string $address): string => $this->canonicalIp($address),
            $addresses,
        )));

        if (! $allowPrivate) {
            foreach ($addresses as $address) {
                if (! $this->isPublicIp($address)) {
                    throw new UnsafeMirrorUrlException(
                        "Mirror URL host {$host} resolves to a private or reserved address."
                    );
                }
            }
        }

        $scheme = strtolower($uri->getScheme());
        $port = $uri->getPort() ?? ($scheme === 'https' ? 443 : 80);
        $urlHost = str_contains($host, ':') ? "[{$host}]" : $host;
        $normalizedUri = $uri->withScheme($scheme)->withHost($urlHost);

        return new ResolvedMirrorUrl(
            url: (string) $normalizedUri,
            host: $host,
            port: $port,
            addresses: $addresses,
            literalHost: $literalHost,
        );
    }

    private function parse(string $url): UriInterface
    {
        try {
            $uri = new Uri(trim($url));
        } catch (Throwable) {
            throw new UnsafeMirrorUrlException('The mirror URL is invalid.');
        }

        if (! in_array(strtolower($uri->getScheme()), ['http', 'https'], true) || $uri->getHost() === '') {
            throw new UnsafeMirrorUrlException('The mirror URL must be an absolute HTTP or HTTPS URL.');
        }

        if ($uri->getUserInfo() !== '') {
            throw new UnsafeMirrorUrlException('The mirror URL must not contain embedded credentials.');
        }

        $host = $this->canonicalHost($uri);
        $urlHost = str_contains($host, ':') ? "[{$host}]" : $host;

        return $uri
            ->withScheme(strtolower($uri->getScheme()))
            ->withHost($urlHost);
    }

    private function canonicalHost(UriInterface $uri): string
    {
        $host = strtolower(rtrim(trim($uri->getHost(), '[]'), '.'));

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        $asciiHost = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        if ($asciiHost === false || $asciiHost === '' || ! filter_var($asciiHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new UnsafeMirrorUrlException('The mirror URL contains an invalid hostname.');
        }

        return strtolower($asciiHost);
    }

    private function canonicalIp(string $address): string
    {
        $address = trim($address, '[]');
        $packed = @inet_pton($address);

        if ($packed === false) {
            throw new UnsafeMirrorUrlException('The mirror hostname resolved to an invalid address.');
        }

        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            return (string) inet_ntop(substr($packed, 12));
        }

        return (string) inet_ntop($packed);
    }

    private function isPublicIp(string $address): bool
    {
        if (filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            return false;
        }

        $ranges = str_contains($address, ':')
            ? self::BLOCKED_IPV6_RANGES
            : self::BLOCKED_IPV4_RANGES;

        return ! IpUtils::checkIp($address, $ranges);
    }

    private function isAllowedPrivateHost(string $host): bool
    {
        $allowedHosts = config('pricore.mirrors.allowed_private_hosts', []);

        if (! is_array($allowedHosts)) {
            return false;
        }

        foreach ($allowedHosts as $allowedHost) {
            if (! is_string($allowedHost) || str_contains($allowedHost, '*') || str_contains($allowedHost, '/')) {
                continue;
            }

            $candidate = strtolower(rtrim(trim($allowedHost, " \t\n\r\0\x0B[]"), '.'));

            if ($candidate === $host) {
                return true;
            }
        }

        return false;
    }
}
