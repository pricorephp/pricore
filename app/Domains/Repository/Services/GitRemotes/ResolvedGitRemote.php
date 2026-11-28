<?php

namespace App\Domains\Repository\Services\GitRemotes;

readonly class ResolvedGitRemote
{
    public const REF_ADVERTISEMENT = 'info/refs?service=git-upload-pack';

    /**
     * @param  string  $url  The address to hand to git
     * @param  array<int, string>  $addresses  Addresses the connection is held to; empty when the host is used as given
     */
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public ?int $port = null,
        public array $addresses = [],
    ) {}

    public function isHttp(): bool
    {
        return $this->scheme === 'http' || $this->scheme === 'https';
    }

    /**
     * The first request git makes to an HTTP remote, without the URL's credentials.
     */
    public function refAdvertisementUrl(): string
    {
        $url = (string) preg_replace('#^(https?://)[^/?\#]*@#i', '$1', $this->url);

        return (str_ends_with($url, '/') ? $url : "{$url}/").self::REF_ADVERTISEMENT;
    }

    /**
     * The credentials an HTTP URL carries, decoded.
     *
     * @return array{0: string, 1: string}|null
     */
    public function httpCredentials(): ?array
    {
        if (! $this->isHttp() || preg_match('#^https?://([^/?\#@]*)@#i', $this->url, $matches) !== 1) {
            return null;
        }

        [$username, $password] = array_pad(explode(':', $matches[1], 2), 2, '');

        return [rawurldecode($username), rawurldecode($password)];
    }

    /**
     * Options that go between `git` and the subcommand. The redirect target is
     * settled before git runs, so git must not follow one on its own.
     *
     * @return array<int, string>
     */
    public function gitOptions(): array
    {
        if (! $this->isHttp()) {
            return [];
        }

        $options = ['-c', 'http.followRedirects=false'];

        if ($this->curlResolveEntry() !== null) {
            $options[] = '-c';
            $options[] = 'http.curloptResolve='.$this->curlResolveEntry();
        }

        return $options;
    }

    /**
     * Holds an HTTP connection to the addresses that were checked, so the
     * hostname cannot resolve to another one in between.
     */
    public function curlResolveEntry(): ?string
    {
        if (! $this->isHttp() || $this->addresses === []) {
            return null;
        }

        $port = $this->port ?? ($this->scheme === 'https' ? 443 : 80);

        $addresses = array_map(
            static fn (string $address): string => str_contains($address, ':') ? "[{$address}]" : $address,
            $this->addresses,
        );

        return "{$this->host}:{$port}:".implode(',', $addresses);
    }

    /**
     * Environment for the git process. A git:// URL names the checked address,
     * so the server is still told which hostname was asked for.
     *
     * @return array<string, string>
     */
    public function environment(): array
    {
        if ($this->scheme !== 'git' || $this->addresses === []) {
            return [];
        }

        return ['GIT_OVERRIDE_VIRTUAL_HOST' => $this->port === null ? $this->host : "{$this->host}:{$this->port}"];
    }

    /**
     * Options to append to the ssh command git runs. The host key is still
     * looked up under the name ssh would have used for the hostname.
     */
    public function sshOptions(): string
    {
        if ($this->scheme !== 'ssh' || $this->addresses === []) {
            return '';
        }

        $hostKeyAlias = $this->port === null || $this->port === 22
            ? $this->host
            : "[{$this->host}]:{$this->port}";

        return ' -o '.escapeshellarg('HostName='.static::preferredAddress($this->addresses))
            .' -o '.escapeshellarg("HostKeyAlias={$hostKeyAlias}");
    }

    /**
     * A single address for transports that take only one. IPv4 comes first, as
     * a server is more often without an IPv6 route than without an IPv4 one.
     *
     * @param  non-empty-array<int, string>  $addresses
     */
    public static function preferredAddress(array $addresses): string
    {
        foreach ($addresses as $address) {
            if (! str_contains($address, ':')) {
                return $address;
            }
        }

        return $addresses[0];
    }
}
