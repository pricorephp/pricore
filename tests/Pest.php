<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a zip archive at $path. Values are file contents; null adds a directory entry.
 *
 * @param  array<string, string|null>  $entries
 */
function createTestZip(string $path, array $entries): void
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($entries as $name => $contents) {
        $contents === null ? $zip->addEmptyDir($name) : $zip->addFromString($name, $contents);
    }

    $zip->close();
}

/**
 * Entries of a zip archive sorted by name: file contents, or null for a directory entry.
 *
 * @return array<string, string|null>
 */
function readTestZip(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);

    $entries = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = (string) $zip->getNameIndex($index);
        $entries[$name] = str_ends_with($name, '/') ? null : (string) $zip->getFromIndex($index);
    }

    $zip->close();
    ksort($entries);

    return $entries;
}

/**
 * Sorted names of the file entries in a zip archive, ignoring directory entries.
 *
 * @return array<int, string>
 */
function testZipFileNames(string $path): array
{
    return array_keys(array_filter(readTestZip($path), fn (?string $contents) => $contents !== null));
}
