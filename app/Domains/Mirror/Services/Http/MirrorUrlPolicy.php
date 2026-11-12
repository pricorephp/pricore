<?php

namespace App\Domains\Mirror\Services\Http;

use App\Domains\Mirror\Contracts\Interfaces\HostResolverInterface;
use App\Domains\Mirror\Exceptions\UnsafeMirrorUrlException;
use App\Services\Network\IpAddressClassifier;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriComparator;
use Psr\Http\Message\UriInterface;
use Throwable;

class MirrorUrlPolicy
{
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
        return IpAddressClassifier::canonical($address)
            ?? throw new UnsafeMirrorUrlException('The mirror hostname resolved to an invalid address.');
    }

    private function isPublicIp(string $address): bool
    {
        return IpAddressClassifier::isPublic($address);
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
