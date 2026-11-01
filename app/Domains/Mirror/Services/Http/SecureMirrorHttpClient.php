<?php

namespace App\Domains\Mirror\Services\Http;

use App\Domains\Mirror\Contracts\Enums\MirrorAuthType;
use App\Domains\Mirror\Exceptions\UnsafeMirrorUrlException;
use App\Models\Mirror;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class SecureMirrorHttpClient
{
    private const MAX_REDIRECTS = 5;

    public function __construct(
        private readonly Mirror $mirror,
        private readonly MirrorUrlPolicy $urlPolicy,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function get(string $url, array $options = []): Response
    {
        $redirects = 0;

        while (true) {
            $resolvedUrl = $this->urlPolicy->resolveForMirror($url, $this->mirror->url);
            $response = $this->request($resolvedUrl, $options);
            $location = $response->header('Location');

            if (! $response->redirect() || $location === '') {
                return $response;
            }

            if ($redirects >= self::MAX_REDIRECTS) {
                throw new UnsafeMirrorUrlException('Mirror request exceeded the maximum number of redirects.');
            }

            try {
                $url = (string) UriResolver::resolve(new Uri($resolvedUrl->url), new Uri($location));
            } catch (Throwable) {
                throw new UnsafeMirrorUrlException('Mirror response contained an invalid redirect URL.');
            }

            $redirects++;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function request(ResolvedMirrorUrl $url, array $options): Response
    {
        $curlOptions = $url->curlResolveEntries() === []
            ? []
            : [CURLOPT_RESOLVE => $url->curlResolveEntries()];
        $options['allow_redirects'] = false;
        $options['curl'] = $curlOptions;
        // An HTTP proxy would resolve the target itself and bypass the pinned
        // DNS result above, so mirror traffic must connect directly.
        $options['proxy'] = '';

        $request = Http::timeout(30)
            ->connectTimeout(10)
            ->withUserAgent('Pricore Mirror Sync')
            ->withOptions($options);

        if ($this->urlPolicy->isSameOrigin($url->url, $this->mirror->url)) {
            $request = $this->withMirrorCredentials($request);
        }

        return $request->get($url->url);
    }

    private function withMirrorCredentials(PendingRequest $request): PendingRequest
    {
        $credentials = $this->mirror->auth_credentials;

        return match ($this->mirror->auth_type) {
            MirrorAuthType::Basic => $request->withBasicAuth(
                $credentials['username'] ?? '',
                $credentials['password'] ?? '',
            ),
            MirrorAuthType::Bearer => $request->withToken(
                $credentials['token'] ?? '',
            ),
            MirrorAuthType::None => $request,
        };
    }
}
