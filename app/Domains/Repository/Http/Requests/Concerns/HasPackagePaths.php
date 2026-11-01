<?php

namespace App\Domains\Repository\Http\Requests\Concerns;

use App\Domains\Repository\Rules\ValidPackagePath;
use App\Domains\Repository\Services\PackagePaths\PackagePathPattern;

/**
 * Package paths arrive as a textarea (one pattern per line) or as a list.
 * Both are normalised into a list before validation, so every entry is
 * checked individually and stored in canonical form.
 */
trait HasPackagePaths
{
    protected function prepareForValidation(): void
    {
        if ($this->has('package_paths')) {
            $this->merge([
                'package_paths' => PackagePathPattern::parseInput($this->input('package_paths')),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function packagePathRules(): array
    {
        return [
            'package_paths' => ['nullable', 'array', 'max:50'],
            'package_paths.*' => ['string', 'max:255', new ValidPackagePath],
        ];
    }

    /**
     * @return array<int, string>|null
     */
    public function packagePaths(): ?array
    {
        $paths = $this->validated('package_paths');

        if (! is_array($paths) || $paths === []) {
            return null;
        }

        return array_values(array_map(fn (mixed $path): string => (string) $path, $paths));
    }
}
