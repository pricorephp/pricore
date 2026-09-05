<?php

namespace App\Domains\Repository\Services\Archive;

use ZipArchive;

/**
 * Cuts one subdirectory out of a repository archive.
 *
 * Provider archive endpoints (GitHub zipball, GitLab archive, Bitbucket get) have
 * no path filter and wrap the whole tree in a single top-level directory such as
 * owner-repo-abc1234/. A monorepo package needs only its own subtree, re-rooted so
 * Composer receives an ordinary single-folder package archive.
 */
class ZipSubtreeExtractor
{
    /**
     * Top-level folder for a subtree archive, mirroring provider archives:
     * the directory's own name plus a short commit reference.
     */
    public static function prefixFor(string $path, string $ref): string
    {
        $name = basename(trim($path, '/'));

        return ($name === '' ? 'package' : $name).'-'.substr($ref, 0, 12);
    }

    /**
     * Write the entries below $path in $sourcePath to $outputPath, rooted under $prefix.
     * Returns false when the subtree contains no files; no output file is left behind.
     */
    public function extract(string $sourcePath, string $outputPath, string $path, string $prefix): bool
    {
        $source = new ZipArchive;

        if ($source->open($sourcePath) !== true) {
            return false;
        }

        try {
            $entries = $this->collectEntries($source, $this->subtreeRoot($source, trim($path, '/')));

            if ($entries === []) {
                return false;
            }

            return $this->writeArchive($source, $outputPath, $entries, trim($prefix, '/'));
        } finally {
            $source->close();
        }
    }

    /**
     * Archive-internal prefix shared by every entry of the subtree,
     * e.g. "owner-repo-abc1234/packages/billing/".
     */
    protected function subtreeRoot(ZipArchive $source, string $path): string
    {
        $topLevel = $this->detectTopLevelDirectory($source);
        $root = $topLevel === null ? '' : "{$topLevel}/";

        return $path === '' ? $root : "{$root}{$path}/";
    }

    /**
     * The single directory wrapping every entry, or null when entries sit at the root.
     */
    protected function detectTopLevelDirectory(ZipArchive $source): ?string
    {
        $topLevel = null;

        for ($index = 0; $index < $source->numFiles; $index++) {
            $name = $source->getNameIndex($index);

            if ($name === false || $name === '') {
                continue;
            }

            $slash = strpos($name, '/');

            if ($slash === false) {
                return null;
            }

            $segment = substr($name, 0, $slash);

            if ($topLevel === null) {
                $topLevel = $segment;
            } elseif ($topLevel !== $segment) {
                return null;
            }
        }

        return $topLevel;
    }

    /**
     * @return array<int, array{index: int, relative: string, isDirectory: bool}>
     */
    protected function collectEntries(ZipArchive $source, string $subtreeRoot): array
    {
        $entries = [];
        $hasFiles = false;

        for ($index = 0; $index < $source->numFiles; $index++) {
            $name = $source->getNameIndex($index);

            if ($name === false || ! str_starts_with($name, $subtreeRoot)) {
                continue;
            }

            $relative = substr($name, strlen($subtreeRoot));

            if ($relative === '' || $this->escapesSubtree($relative)) {
                continue;
            }

            $isDirectory = str_ends_with($relative, '/');
            $hasFiles = $hasFiles || ! $isDirectory;

            $entries[] = ['index' => $index, 'relative' => $relative, 'isDirectory' => $isDirectory];
        }

        return $hasFiles ? $entries : [];
    }

    /**
     * @param  array<int, array{index: int, relative: string, isDirectory: bool}>  $entries
     */
    protected function writeArchive(ZipArchive $source, string $outputPath, array $entries, string $prefix): bool
    {
        $output = new ZipArchive;

        if ($output->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        foreach ($entries as $entry) {
            $target = $prefix === '' ? $entry['relative'] : "{$prefix}/{$entry['relative']}";

            if ($entry['isDirectory']) {
                $output->addEmptyDir($target);

                continue;
            }

            $contents = $source->getFromIndex($entry['index']);

            if ($contents === false) {
                $output->unchangeAll();
                $output->close();
                $this->removeFile($outputPath);

                return false;
            }

            $output->addFromString($target, $contents);
            $this->copyUnixMode($source, $entry['index'], $output, $target);
        }

        return $output->close() && file_exists($outputPath);
    }

    /**
     * Keep the executable bits (bin scripts) that the provider archive recorded.
     */
    protected function copyUnixMode(ZipArchive $source, int $index, ZipArchive $output, string $target): void
    {
        $opsys = 0;
        $attributes = 0;

        if ($source->getExternalAttributesIndex($index, $opsys, $attributes) && $opsys === ZipArchive::OPSYS_UNIX) {
            $output->setExternalAttributesName($target, ZipArchive::OPSYS_UNIX, $attributes);
        }
    }

    protected function escapesSubtree(string $relative): bool
    {
        if (str_contains($relative, "\0") || str_starts_with($relative, '/')) {
            return true;
        }

        return in_array('..', explode('/', $relative), true);
    }

    protected function removeFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
