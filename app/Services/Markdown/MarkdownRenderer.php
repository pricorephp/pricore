<?php

namespace App\Services\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\AbstractWebResource;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Node;

class MarkdownRenderer
{
    /**
     * Render markdown to safe HTML, optionally rewriting relative <a> and <img> URLs
     * to absolute Git-provider URLs so README links and images resolve correctly.
     *
     * Pass null base URLs (generic Git provider) to leave relative URLs untouched.
     */
    public function render(
        string $markdown,
        ?string $blobBaseUrl = null,
        ?string $rawFileBaseUrl = null,
    ): string {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'renderer' => [
                'soft_break' => "\n",
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new AutolinkExtension);

        if ($blobBaseUrl !== null || $rawFileBaseUrl !== null) {
            $environment->addEventListener(
                DocumentParsedEvent::class,
                fn (DocumentParsedEvent $event) => $this->rewriteUrls(
                    $event->getDocument(),
                    $blobBaseUrl,
                    $rawFileBaseUrl,
                ),
            );
        }

        return (string) (new MarkdownConverter($environment))->convert($markdown);
    }

    protected function rewriteUrls(Node $document, ?string $blobBaseUrl, ?string $rawFileBaseUrl): void
    {
        foreach ($document->iterator() as $node) {
            if ($node instanceof Image && $rawFileBaseUrl !== null) {
                $this->rewriteIfRelative($node, $rawFileBaseUrl);
            } elseif ($node instanceof Link && $blobBaseUrl !== null) {
                $this->rewriteIfRelative($node, $blobBaseUrl);
            }
        }
    }

    protected function rewriteIfRelative(AbstractWebResource $node, string $baseUrl): void
    {
        $url = $node->getUrl();

        if ($url === '' || $this->isAbsolute($url)) {
            return;
        }

        $node->setUrl(rtrim($baseUrl, '/').'/'.ltrim($url, '/'));
    }

    protected function isAbsolute(string $url): bool
    {
        // Absolute URL (any scheme), protocol-relative URL, or in-page anchor.
        return preg_match('~^(?:[a-z][a-z0-9+.\-]*:|//|#)~i', $url) === 1;
    }
}
