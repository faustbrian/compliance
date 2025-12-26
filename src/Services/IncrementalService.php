<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use Throwable;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function getcwd;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;

/**
 * Service for tracking test file changes and enabling incremental test runs.
 *
 * Stores modification times of test files between runs, allowing the test
 * runner to execute only tests that have changed since the last run. This
 * significantly speeds up development workflows for large test suites.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class IncrementalService
{
    /**
     * Create a new incremental service instance.
     *
     * @param null|string $cacheDir Directory path for storing incremental run cache files.
     *                              Defaults to .prism/cache/ in the current working directory
     *                              if not specified. Used to store file modification timestamps
     *                              between test runs for efficient change detection and
     *                              selective test execution.
     */
    public function __construct(
        private ?string $cacheDir = null,
    ) {}

    /**
     * Store modification times for test files.
     *
     * Captures and persists the current modification time (mtime) for each test file
     * to enable incremental testing. Stores timestamps as JSON in the cache directory,
     * creating the directory if needed. Skips files that cannot be stat'd.
     *
     * @param array<int, string> $testFiles Array of test file paths to track. Should include
     *                                      all test files that were processed in this run
     */
    public function saveCache(array $testFiles): void
    {
        $cache = [];

        foreach ($testFiles as $file) {
            $mtime = filemtime($file);

            if ($mtime === false) {
                continue;
            }

            $cache[$file] = $mtime;
        }

        $this->ensureCacheDir();

        $cachePath = $this->getCachePath();
        file_put_contents($cachePath, json_encode($cache, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /**
     * Filter test files to only include those that have changed.
     *
     * Compares current file modification times against cached values to identify
     * changed or new files. Returns all files on first run (no cache exists) or
     * when cache is corrupted. If no changes are detected, returns all files to
     * prevent empty test runs (safety fallback).
     *
     * @param  array<int, string> $testFiles All test files to check for changes
     * @return array<int, string> Files that have changed since last run, or all files
     *                            if no cache exists or no changes detected
     */
    public function filterChangedFiles(array $testFiles): array
    {
        $cachePath = $this->getCachePath();

        if (!file_exists($cachePath)) {
            // No cache exists, run all tests
            return $testFiles;
        }

        $contents = file_get_contents($cachePath);

        if ($contents === false) {
            return $testFiles;
        }

        try {
            $cache = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $testFiles;
        }

        if (!is_array($cache)) {
            return $testFiles;
        }

        $changedFiles = [];

        foreach ($testFiles as $file) {
            $currentMtime = filemtime($file);

            if ($currentMtime === false) {
                continue;
            }

            // File is new or has been modified
            if (isset($cache[$file]) && $cache[$file] === $currentMtime) {
                continue;
            }

            $changedFiles[] = $file;
        }

        // If no changes detected, return all files (prevent empty runs)
        return $changedFiles === [] ? $testFiles : $changedFiles;
    }

    /**
     * Get path to cache file.
     *
     * @return string Absolute file path to the incremental cache JSON file
     */
    private function getCachePath(): string
    {
        $cacheDir = $this->cacheDir ?? (getcwd() ?: '.').'/.prism/cache';

        return $cacheDir.'/incremental.json';
    }

    /**
     * Ensure cache directory exists.
     *
     * Creates the cache directory with appropriate permissions (0755) if it
     * doesn't already exist. Required before writing cache files to prevent
     * file I/O errors.
     */
    private function ensureCacheDir(): void
    {
        $cacheDir = $this->cacheDir ?? (getcwd() ?: '.').'/.prism/cache';

        if (is_dir($cacheDir)) {
            return;
        }

        mkdir($cacheDir, 0o755, true);
    }
}
