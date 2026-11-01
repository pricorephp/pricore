<?php

use App\Domains\Package\Exceptions\ArtifactRejectedException;
use App\Domains\Package\Services\Artifact\ArtifactInspector;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/pricore-artifact-'.bin2hex(random_bytes(6));
    mkdir($this->directory);
    $this->archive = $this->directory.'/archive.zip';
    $this->artifactInspector = new ArtifactInspector;
});

afterEach(function () {
    foreach (glob($this->directory.'/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($this->directory);
});

it('reads composer.json and the readme from the archive root', function () {
    createTestZip($this->archive, [
        'composer.json' => '{"name": "acme/legacy", "version": "1.0.0"}',
        'README.md' => '# Legacy',
        'src/Legacy.php' => '<?php',
    ]);

    $contents = $this->artifactInspector->inspect($this->archive);

    expect($contents->composerJson)->toBe(['name' => 'acme/legacy', 'version' => '1.0.0'])
        ->and($contents->readme)->toBe('# Legacy');
});

it('reads composer.json from a single top-level directory', function () {
    createTestZip($this->archive, [
        'legacy-1.0.0/' => null,
        'legacy-1.0.0/composer.json' => '{"name": "acme/legacy"}',
        'legacy-1.0.0/readme.markdown' => 'Legacy docs',
    ]);

    $contents = $this->artifactInspector->inspect($this->archive);

    expect($contents->composerJson['name'])->toBe('acme/legacy')
        ->and($contents->readme)->toBe('Legacy docs');
});

it('returns no readme when the archive has none', function () {
    createTestZip($this->archive, ['composer.json' => '{"name": "acme/legacy"}']);

    expect($this->artifactInspector->inspect($this->archive)->readme)->toBeNull();
});

it('skips a readme above the size cap', function () {
    createTestZip($this->archive, [
        'composer.json' => '{"name": "acme/legacy"}',
        'README.md' => str_repeat('a', 512 * 1024 + 1),
    ]);

    expect($this->artifactInspector->inspect($this->archive)->readme)->toBeNull();
});

it('rejects files that are not zip archives', function () {
    file_put_contents($this->archive, 'not a zip');

    $this->artifactInspector->inspect($this->archive);
})->throws(ArtifactRejectedException::class, 'not a valid zip archive');

it('rejects archives without a composer.json', function (array $entries) {
    createTestZip($this->archive, $entries);

    $this->artifactInspector->inspect($this->archive);
})->with([
    'no composer.json' => [['src/Legacy.php' => '<?php']],
    'nested two levels deep' => [['a/b/composer.json' => '{"name": "acme/legacy"}']],
    'next to other top-level entries' => [['a/composer.json' => '{"name": "acme/legacy"}', 'b/other.txt' => 'x']],
])->throws(ArtifactRejectedException::class, 'no composer.json');

it('rejects invalid composer.json', function (string $composerJson, string $message) {
    createTestZip($this->archive, ['composer.json' => $composerJson]);

    expect(fn () => $this->artifactInspector->inspect($this->archive))
        ->toThrow(ArtifactRejectedException::class, $message);
})->with([
    'broken JSON' => ['{"name": ', 'not valid JSON'],
    'a list' => ['["acme/legacy"]', 'must be a JSON object'],
    'a string' => ['"acme/legacy"', 'must be a JSON object'],
]);

it('rejects entries that would escape the install directory', function (string $name) {
    createTestZip($this->archive, [
        'composer.json' => '{"name": "acme/legacy"}',
        $name => 'payload',
    ]);

    $this->artifactInspector->inspect($this->archive);
})->with([
    '../outside.php',
    'src/../../outside.php',
    '/etc/cron.d/job',
    'C:/Windows/evil.dll',
    'src\\..\\..\\outside.php',
])->throws(ArtifactRejectedException::class, 'unsafe paths');

it('rejects symbolic links', function () {
    $zip = new ZipArchive;
    $zip->open($this->archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('composer.json', '{"name": "acme/legacy"}');
    $zip->addFromString('link', '/etc/passwd');
    $zip->setExternalAttributesName('link', ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->close();

    $this->artifactInspector->inspect($this->archive);
})->throws(ArtifactRejectedException::class, 'symbolic links');
