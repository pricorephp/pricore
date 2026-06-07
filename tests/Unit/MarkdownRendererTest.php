<?php

use App\Services\Markdown\MarkdownRenderer;

beforeEach(function () {
    $this->renderer = new MarkdownRenderer;
});

it('renders headings, lists, and code blocks', function () {
    $html = $this->renderer->render(<<<'MD'
# Heading

- one
- two

```php
echo 'hi';
```
MD);

    expect($html)
        ->toContain('<h1>Heading</h1>')
        ->toContain('<li>one</li>')
        ->toContain('<pre><code class="language-php">');
});

it('renders GFM tables', function () {
    $html = $this->renderer->render(<<<'MD'
| A | B |
|---|---|
| 1 | 2 |
MD);

    expect($html)
        ->toContain('<table>')
        ->toContain('<th>A</th>')
        ->toContain('<td>1</td>');
});

it('escapes inline HTML to prevent XSS', function () {
    $html = $this->renderer->render('Hello <script>alert(1)</script>');

    expect($html)
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});

it('rewrites relative image URLs using the raw file base URL', function () {
    $html = $this->renderer->render(
        '![logo](docs/logo.png)',
        blobBaseUrl: 'https://github.com/vendor/pkg/blob/main/',
        rawFileBaseUrl: 'https://raw.githubusercontent.com/vendor/pkg/main/',
    );

    expect($html)->toContain(
        'src="https://raw.githubusercontent.com/vendor/pkg/main/docs/logo.png"'
    );
});

it('rewrites relative link URLs using the blob base URL', function () {
    $html = $this->renderer->render(
        '[contributing](CONTRIBUTING.md)',
        blobBaseUrl: 'https://github.com/vendor/pkg/blob/main/',
        rawFileBaseUrl: 'https://raw.githubusercontent.com/vendor/pkg/main/',
    );

    expect($html)->toContain(
        'href="https://github.com/vendor/pkg/blob/main/CONTRIBUTING.md"'
    );
});

it('does not touch absolute URLs or in-page anchors', function () {
    $html = $this->renderer->render(
        '[abs](https://example.com/x) [anchor](#section)',
        blobBaseUrl: 'https://github.com/vendor/pkg/blob/main/',
        rawFileBaseUrl: 'https://raw.githubusercontent.com/vendor/pkg/main/',
    );

    expect($html)
        ->toContain('href="https://example.com/x"')
        ->toContain('href="#section"');
});

it('leaves relative URLs untouched when no base URLs are provided', function () {
    $html = $this->renderer->render('[doc](docs/x.md)');

    expect($html)->toContain('href="docs/x.md"');
});
