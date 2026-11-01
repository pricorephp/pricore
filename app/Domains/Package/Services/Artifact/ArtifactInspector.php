<?php

namespace App\Domains\Package\Services\Artifact;

use App\Domains\Package\Contracts\Data\ArtifactContentsData;
use App\Domains\Package\Exceptions\ArtifactRejectedException;
use ZipArchive;

/**
 * Reads the composer.json and README out of an uploaded package archive.
 *
 * The archive is never extracted: only those two entries are read, each under
 * a size cap, so a zip bomb costs no more than its central directory. Every
 * entry is still checked for paths that would escape the install directory,
 * because Composer extracts the archive as-is on developer machines and CI.
 */
class ArtifactInspector
{
    protected const MAX_ENTRIES = 50_000;

    protected const MAX_COMPOSER_JSON_BYTES = 1024 * 1024;

    protected const MAX_README_BYTES = 512 * 1024;

    /**
     * In order of preference, compared case-insensitively.
     */
    protected const README_FILENAMES = [
        'readme.md',
        'readme.markdown',
        'readme',
    ];

    protected const UNIX_FILE_TYPE_MASK = 0170000;

    protected const UNIX_SYMLINK = 0120000;

    public function inspect(string $path): ArtifactContentsData
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CHECKCONS | ZipArchive::RDONLY) !== true) {
            throw ArtifactRejectedException::invalid('The file is not a valid zip archive.');
        }

        try {
            $names = $this->entryNames($zip);
            $root = $this->packageRoot($names);

            $composerJson = $this->readEntry($zip, "{$root}composer.json", self::MAX_COMPOSER_JSON_BYTES);

            if ($composerJson === null) {
                throw ArtifactRejectedException::invalid('The archive has no composer.json at its root or in a single top-level directory.');
            }

            return new ArtifactContentsData(
                composerJson: $this->decodeComposerJson($composerJson),
                readme: $this->readReadme($zip, $names, $root),
            );
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    protected function entryNames(ZipArchive $zip): array
    {
        if ($zip->numFiles === 0) {
            throw ArtifactRejectedException::invalid('The archive is empty.');
        }

        if ($zip->numFiles > self::MAX_ENTRIES) {
            throw ArtifactRejectedException::invalid('The archive contains too many files.');
        }

        $names = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if ($name === false || $this->isUnsafePath($name) || $this->isSymlink($zip, $index)) {
                throw ArtifactRejectedException::invalid('The archive contains unsafe paths or symbolic links.');
            }

            $names[] = $name;
        }

        return $names;
    }

    protected function isUnsafePath(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0") || preg_match('#^([a-zA-Z]:)?[/\\\\]#', $name) === 1) {
            return true;
        }

        return in_array('..', preg_split('#[/\\\\]#', $name) ?: [], true);
    }

    protected function isSymlink(ZipArchive $zip, int $index): bool
    {
        $operatingSystem = 0;
        $attributes = 0;

        if (! $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
            return false;
        }

        return $operatingSystem === ZipArchive::OPSYS_UNIX
            && (($attributes >> 16) & self::UNIX_FILE_TYPE_MASK) === self::UNIX_SYMLINK;
    }

    /**
     * Composer strips a single directory wrapping every entry when it installs
     * an archive, so composer.json may sit either at the root or inside it.
     *
     * @param  list<string>  $names
     */
    protected function packageRoot(array $names): string
    {
        if (in_array('composer.json', $names, true)) {
            return '';
        }

        $topLevel = null;

        foreach ($names as $name) {
            $slash = strpos($name, '/');

            if ($slash === false) {
                return '';
            }

            $segment = substr($name, 0, $slash);

            if ($topLevel !== null && $topLevel !== $segment) {
                return '';
            }

            $topLevel = $segment;
        }

        return $topLevel === null ? '' : "{$topLevel}/";
    }

    protected function readEntry(ZipArchive $zip, string $name, int $maxBytes): ?string
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            return null;
        }

        if ($stat['size'] > $maxBytes) {
            throw ArtifactRejectedException::invalid("{$name} is too large.");
        }

        $contents = $zip->getFromName($name, $maxBytes);

        if ($contents === false) {
            throw ArtifactRejectedException::invalid("{$name} could not be read from the archive.");
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeComposerJson(string $contents): array
    {
        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ArtifactRejectedException::invalid("composer.json is not valid JSON: {$e->getMessage()}");
        }

        if (! is_array($data) || array_is_list($data)) {
            throw ArtifactRejectedException::invalid('composer.json must be a JSON object.');
        }

        return $data;
    }

    /**
     * @param  list<string>  $names
     */
    protected function readReadme(ZipArchive $zip, array $names, string $root): ?string
    {
        $candidates = [];

        foreach ($names as $name) {
            $candidates[strtolower($name)] ??= $name;
        }

        foreach (self::README_FILENAMES as $filename) {
            $name = $candidates[strtolower($root).$filename] ?? null;

            if ($name === null) {
                continue;
            }

            $stat = $zip->statName($name);

            // The README is optional, so an oversized one is skipped rather than rejected
            if ($stat === false || $stat['size'] > self::MAX_README_BYTES) {
                return null;
            }

            $contents = $zip->getFromName($name, self::MAX_README_BYTES);

            if ($contents === false || $contents === '' || ! mb_check_encoding($contents, 'UTF-8')) {
                return null;
            }

            return $contents;
        }

        return null;
    }
}
