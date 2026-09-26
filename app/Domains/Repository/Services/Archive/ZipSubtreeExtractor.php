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

        } finally {
            $source->close();
        }

        return $this->writeArchive($sourcePath, $outputPath, $entries, trim($prefix, '/'));
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
     * Copies the source archive and edits the copy in place: entries outside the
     * subtree are dropped and the rest renamed under $prefix. libzip then copies
     * the kept entries raw, so memory stays flat for large packages and each
     * entry keeps its timestamp and mode, which keeps rebuilds byte-identical.
     *
     * @param  array<int, array{index: int, relative: string, isDirectory: bool}>  $entries
     */
    protected function writeArchive(string $sourcePath, string $outputPath, array $entries, string $prefix): bool
    {
        if (! copy($sourcePath, $outputPath)) {
            return false;
        }

        $output = new ZipArchive;

        if ($output->open($outputPath) !== true) {
            $this->removeFile($outputPath);

            return false;
        }

        $targets = [];

        foreach ($entries as $entry) {
            $targets[$entry['index']] = $prefix === '' ? $entry['relative'] : "{$prefix}/{$entry['relative']}";
        }

        $edited = $output->setArchiveComment('');

        for ($index = 0; $edited && $index < $output->numFiles; $index++) {
            $edited = isset($targets[$index])
                ? $output->renameIndex($index, $targets[$index])
                : $output->deleteIndex($index);
        }

        if (! $edited) {
            $output->unchangeAll();
            $output->close();
            $this->removeFile($outputPath);

            return false;
        }

        return $output->close() && file_exists($outputPath);
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
