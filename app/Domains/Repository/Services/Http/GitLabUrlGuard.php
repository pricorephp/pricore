<?php

namespace App\Domains\Repository\Services\Http;

use App\Domains\Mirror\Exceptions\UnsafeMirrorUrlException;
use App\Domains\Mirror\Services\Http\MirrorUrlPolicy;
use App\Domains\Mirror\Services\Http\ResolvedMirrorUrl;
use App\Domains\Repository\Exceptions\GitProviderException;
use Closure;
use Psr\Http\Message\RequestInterface;

/**
 * The GitLab base URL is chosen by the user, so requests to it are held to the
 * same rules as mirror traffic: no private or reserved addresses unless the
 * host is explicitly allowed, checked again on every redirect.
 */
class GitLabUrlGuard
{
    public function __construct(
        private readonly MirrorUrlPolicy $mirrorUrlPolicy,
    ) {}

    /**
     * @throws UnsafeMirrorUrlException
     */
    public function resolve(string $url): ResolvedMirrorUrl
    {
        return $this->mirrorUrlPolicy->resolveWithAllowedHosts($url, $this->allowedPrivateHosts());
    }

    /**
     * Guzzle middleware. It sits below the redirect middleware, so it also runs
     * for every hop of a redirect chain.
     */
    public function middleware(): Closure
    {
        return fn (callable $handler): Closure => function (RequestInterface $request, array $options) use ($handler) {
            $uri = $request->getUri();

            try {
                $resolvedUrl = $this->resolve((string) $uri->withUserInfo(''));
            } catch (UnsafeMirrorUrlException $exception) {
                throw new GitProviderException(
                    "Refusing to contact {$uri->getHost()}: it is not a public address or an allowed GitLab host.",
                    previous: $exception,
                );
            }

            // Pin the validated DNS result so the connection cannot resolve the
            // hostname to a different address after the check.
            if ($resolvedUrl->curlResolveEntries() !== []) {
                $options['curl'][CURLOPT_RESOLVE] = $resolvedUrl->curlResolveEntries();
            }

            // A proxy would resolve the target itself and bypass the pinned result.
            $options['proxy'] = '';

            return $handler($request, $options);
        };
    }

    /**
     * Shares the allowlist of the Generic Git provider. The instance configured
     * for GitLab sign-in is trusted by the operator as well.
     *
     * @return array<int, mixed>
     */
    private function allowedPrivateHosts(): array
    {
        $allowedHosts = config('pricore.git.allowed_private_hosts', []);
        $instanceHost = parse_url((string) config('services.gitlab.instance_uri'), PHP_URL_HOST);

        return [
            ...(is_array($allowedHosts) ? array_values($allowedHosts) : []),
            ...(is_string($instanceHost) ? [$instanceHost] : []),
        ];
    }
}
