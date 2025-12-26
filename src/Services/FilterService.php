<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use Cline\Prism\ValueObjects\TestResult;

use function fnmatch;
use function in_array;
use function preg_match;

/**
 * Service for filtering test files and test results based on patterns.
 *
 * Supports filtering by test name (regex), file path (glob), tag matching,
 * and excluding tests by name pattern. Used to narrow down test execution
 * to specific subsets of the test suite.
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class FilterService
{
    /**
     * Create a new filter service instance.
     *
     * @param null|string $nameFilter    Regular expression pattern to match test names (group + description).
     *                                   Only tests matching this pattern will be included. Example: '/user.*login/i'
     *                                   for case-insensitive matching of user login tests.
     * @param null|string $pathFilter    Glob pattern to match file paths. Only test files matching this pattern
     *                                   will be processed. Example: 'tests/authentication/*.json' to include only
     *                                   authentication test files. Uses fnmatch() for pattern matching.
     * @param null|string $excludeFilter Regular expression pattern to exclude test names. Tests matching this
     *                                   pattern will be filtered out even if they match the name filter. Applied
     *                                   before the name filter for efficiency. Example: '/deprecated/i' to skip
     *                                   all deprecated tests.
     * @param null|string $tagFilter     Exact tag name to filter by. Only tests with this tag in their tags array
     *                                   will be included. Uses strict string comparison. Example: 'smoke' to run
     *                                   only smoke tests, or 'integration' for integration tests.
     */
    public function __construct(
        private ?string $nameFilter = null,
        private ?string $pathFilter = null,
        private ?string $excludeFilter = null,
        private ?string $tagFilter = null,
    ) {}

    /**
     * Determine if a test file path should be included.
     *
     * Applies glob pattern matching to filter test files by path. When no path
     * filter is configured, all files are included by default.
     *
     * @param  string $filePath Absolute or relative path to test file to check
     * @return bool   True if the file should be processed, false to skip
     */
    public function shouldIncludeFile(string $filePath): bool
    {
        if ($this->pathFilter === null) {
            return true;
        }

        return fnmatch($this->pathFilter, $filePath);
    }

    /**
     * Determine if a test result should be included.
     *
     * Applies multiple filters in sequence: exclude filter (highest priority),
     * tag filter, then name filter. A test must pass all configured filters to
     * be included. Filters are evaluated in order of efficiency, with exclude
     * being checked first to short-circuit expensive operations.
     *
     * @param  TestResult $result Test result to evaluate against filters
     * @return bool       True if the test should be included in results, false to filter out
     */
    public function shouldIncludeTest(TestResult $result): bool
    {
        $testName = $result->group.' - '.$result->description;

        // Apply exclude filter first
        if ($this->excludeFilter !== null && preg_match($this->excludeFilter, $testName) === 1) {
            return false;
        }

        // Apply tag filter
        if ($this->tagFilter !== null && !in_array($this->tagFilter, $result->tags, true)) {
            return false;
        }

        // Apply name filter
        if ($this->nameFilter === null) {
            return true;
        }

        return preg_match($this->nameFilter, $testName) === 1;
    }

    /**
     * Filter an array of test results.
     *
     * Efficiently filters a collection of test results by applying all configured
     * filters. When no filters are configured, returns the original array unchanged
     * for optimal performance. Otherwise, iterates through results and includes only
     * those passing shouldIncludeTest() criteria.
     *
     * @param  array<int, TestResult> $results Test results to filter
     * @return array<int, TestResult> Filtered test results with sequential integer keys
     */
    public function filterResults(array $results): array
    {
        if ($this->nameFilter === null && $this->excludeFilter === null && $this->tagFilter === null) {
            return $results;
        }

        $filtered = [];

        foreach ($results as $result) {
            if (!$this->shouldIncludeTest($result)) {
                continue;
            }

            $filtered[] = $result;
        }

        return $filtered;
    }
}
