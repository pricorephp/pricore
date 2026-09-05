<?php

use App\Domains\Repository\Services\Archive\ZipSubtreeExtractor;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/pricore-zip-'.bin2hex(random_bytes(6));
    mkdir($this->directory);
    $this->source = $this->directory.'/source.zip';
    $this->output = $this->directory.'/output.zip';
    $this->extractor = new ZipSubtreeExtractor;
});

afterEach(function () {
    foreach (glob($this->directory.'/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($this->directory);
});

it('cuts a subdirectory out of a provider archive and re-roots it under the prefix', function () {
    createTestZip($this->source, [
        'acme-monorepo-abc1234/composer.json' => '{"name": "acme/monorepo"}',
        'acme-monorepo-abc1234/packages/billing/composer.json' => '{"name": "acme/billing"}',
        'acme-monorepo-abc1234/packages/billing/src/Invoice.php' => '<?php // invoice',
        'acme-monorepo-abc1234/packages/crm/composer.json' => '{"name": "acme/crm"}',
    ]);

    $result = $this->extractor->extract($this->source, $this->output, 'packages/billing', 'billing-abc123def456');

    expect($result)->toBeTrue()
        ->and(readTestZip($this->output))->toBe([
            'billing-abc123def456/composer.json' => '{"name": "acme/billing"}',
            'billing-abc123def456/src/Invoice.php' => '<?php // invoice',
        ]);
});

it('keeps directory entries inside the subtree', function () {
    createTestZip($this->source, [
        'top/' => null,
        'top/packages/' => null,
        'top/packages/billing/' => null,
        'top/packages/billing/src/' => null,
        'top/packages/billing/src/A.php' => 'a',
    ]);

    expect($this->extractor->extract($this->source, $this->output, 'packages/billing', 'billing-x'))->toBeTrue()
        ->and(array_keys(readTestZip($this->output)))->toBe([
            'billing-x/src/',
            'billing-x/src/A.php',
        ]);
});

it('handles archives whose entries sit at the root', function () {
    createTestZip($this->source, [
        'composer.json' => 'root',
        'packages/billing/composer.json' => 'billing',
    ]);

    expect($this->extractor->extract($this->source, $this->output, 'packages/billing', 'billing-x'))->toBeTrue()
        ->and(readTestZip($this->output))->toBe(['billing-x/composer.json' => 'billing']);
});

it('re-roots the whole tree when the path is empty', function () {
    createTestZip($this->source, [
        'top/composer.json' => 'root',
        'top/src/A.php' => 'a',
    ]);

    expect($this->extractor->extract($this->source, $this->output, '', 'pkg-x'))->toBeTrue()
        ->and(array_keys(readTestZip($this->output)))->toBe(['pkg-x/composer.json', 'pkg-x/src/A.php']);
});

it('returns false and leaves no file when the subdirectory does not exist', function () {
    createTestZip($this->source, ['top/composer.json' => 'root']);

    expect($this->extractor->extract($this->source, $this->output, 'packages/billing', 'billing-x'))->toBeFalse()
        ->and(file_exists($this->output))->toBeFalse();
});

it('returns false when the subdirectory holds no files', function () {
    createTestZip($this->source, [
        'top/packages/billing/' => null,
        'top/packages/billing/src/' => null,
    ]);

    expect($this->extractor->extract($this->source, $this->output, 'packages/billing', 'billing-x'))->toBeFalse()
        ->and(file_exists($this->output))->toBeFalse();
});

it('drops entries that escape the subtree', function () {
    createTestZip($this->source, [
        'top/packages/billing/composer.json' => 'ok',
        'top/packages/billing/../crm/composer.json' => 'escaped',
    ]);

    expect($this->extractor->extract($this->source, $this->output, 'packages/billing', 'billing-x'))->toBeTrue()
        ->and(array_keys(readTestZip($this->output)))->toBe(['billing-x/composer.json']);
});

it('returns false for an unreadable source archive', function () {
    file_put_contents($this->source, 'not a zip');

    expect($this->extractor->extract($this->source, $this->output, 'packages/billing', 'billing-x'))->toBeFalse();
});

it('builds the archive prefix from the directory name and a short reference', function () {
    expect(ZipSubtreeExtractor::prefixFor('packages/billing', 'abc123def4567890'))->toBe('billing-abc123def456')
        ->and(ZipSubtreeExtractor::prefixFor('', 'abc123def4567890'))->toBe('package-abc123def456');
});
