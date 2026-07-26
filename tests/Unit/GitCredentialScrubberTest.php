<?php

use App\Domains\Repository\Support\GitCredentialScrubber;

it('removes credentials embedded in a git remote url', function () {
    $output = "fatal: could not read from 'https://deploy:s3cr3t@git.example.com/acme/widgets.git'";

    expect(GitCredentialScrubber::scrub($output))
        ->toBe("fatal: could not read from 'https://***@git.example.com/acme/widgets.git'")
        ->not->toContain('s3cr3t');
});

it('removes a bare token used as the userinfo component', function () {
    $output = 'remote: https://glpat-AAAABBBBCCCC@gitlab.example.com/acme/widgets.git not found';

    expect(GitCredentialScrubber::scrub($output))->not->toContain('glpat-AAAABBBBCCCC');
});

it('scrubs every occurrence in multiline output', function () {
    $output = <<<'OUT'
    Cloning into 'widgets'...
    fatal: unable to access 'https://user:one@git.example.com/a.git'
    fatal: unable to access 'https://user:two@git.example.com/b.git'
    OUT;

    $scrubbed = GitCredentialScrubber::scrub($output);

    expect($scrubbed)->not->toContain('one')
        ->and($scrubbed)->not->toContain('two')
        ->and(substr_count($scrubbed, '***@'))->toBe(2);
});

it('leaves output without credentials untouched', function () {
    $output = "fatal: repository 'https://git.example.com/acme/widgets.git' not found";

    expect(GitCredentialScrubber::scrub($output))->toBe($output);
});

it('does not treat a commit message mentioning an email as a credential', function () {
    $output = 'Author: Ada <ada@example.com>';

    expect(GitCredentialScrubber::scrub($output))->toBe($output);
});
