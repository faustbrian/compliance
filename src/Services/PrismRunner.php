<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use Cline\Prism\Contracts\PrismTestInterface;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

use function basename;
use function count;
use function file_get_contents;
use function is_array;
use function is_bool;
use function is_dir;
use function is_string;
use function microtime;
use function sort;
use function sprintf;
use function str_replace;

/**
 * Orchestrates the execution of prism test suites and aggregates results.
 *
 * This service discovers JSON test files within a prism test directory,
 * executes each test case by validating data against schemas, and produces
 * a comprehensive test suite report with timing metrics and pass/fail status.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PrismRunner
{
    /**
     * Create a new prism runner instance.
     *
     * @param null|FilterService          $filterService    Optional service for filtering test files and results
     *                                                      by name patterns, tags, or other criteria to narrow
     *                                                      test execution scope
     * @param null|CustomAssertionService $assertionService Optional service for custom assertion logic that extends
     *                                                      beyond simple expected/actual equality checks, enabling
     *                                                      domain-specific validation rules
     */
    public function __construct(
        private ?FilterService $filterService = null,
        private ?CustomAssertionService $assertionService = null,
    ) {}

    /**
     * Execute a prism test suite and return aggregated results.
     *
     * Discovers all JSON test files in the prism test directory, runs each
     * test case by validating data against schemas, and aggregates the results
     * with total execution time. Test files are processed in sorted order to
     * ensure consistent execution across runs.
     *
     * @param  PrismTestInterface      $prism           The prism test instance defining
     *                                                  the test directory, validation logic,
     *                                                  and JSON decoding behavior for test files
     * @param  null|array<int, string> $fileList        Optional list of specific files to run
     * @param  null|ProgressService    $progressService Optional service for displaying real-time progress
     * @return TestSuite               Complete test suite containing all test results, metadata,
     *                                 and execution duration in seconds
     */
    public function run(PrismTestInterface $prism, ?array $fileList = null, ?ProgressService $progressService = null): TestSuite
    {
        $startTime = microtime(true);
        $results = [];

        $testFiles = $fileList ?? $this->collectTestFiles($prism);

        foreach ($testFiles as $testFile) {
            $fileResults = $this->runTestFile($prism, $testFile, $progressService);
            $results = [...$results, ...$fileResults];
        }

        $duration = microtime(true) - $startTime;

        return new TestSuite(
            name: $prism->getName(),
            results: $results,
            duration: $duration,
        );
    }

    /**
     * Discover all JSON test files in the prism test directory.
     *
     * Recursively scans the test directory for JSON files, filters out
     * non-JSON files, and returns sorted absolute file paths to ensure
     * deterministic test execution order.
     *
     * @param  PrismTestInterface $prism The prism test instance providing
     *                                   the test directory path to scan
     * @return array<int, string> Sorted array of absolute paths to JSON test files,
     *                            or empty array if directory does not exist
     */
    public function collectTestFiles(PrismTestInterface $prism): array
    {
        $testDir = $prism->getTestDirectory();

        if (!is_dir($testDir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($testDir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $testFiles = [];

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            if ($file->getExtension() !== 'json') {
                continue;
            }

            $filePath = $file->getPathname();

            // Allow prism test to filter files (e.g., exclude subdirectories)
            if (!$prism->shouldIncludeFile($filePath)) {
                continue;
            }

            // Apply filter service file filtering
            if ($this->filterService instanceof FilterService && !$this->filterService->shouldIncludeFile($filePath)) {
                continue;
            }

            $testFiles[] = $filePath;
        }

        sort($testFiles);

        return $testFiles;
    }

    /**
     * Execute all test cases within a single JSON test file.
     *
     * Parses the JSON test file to extract test groups, then iterates through
     * each test case to validate data against schemas. Each test result captures
     * whether the validation outcome matches the expected result, along with
     * detailed metadata for reporting. Exceptions during validation are caught
     * and recorded as test failures.
     *
     * @param  PrismTestInterface     $prism           The prism test instance providing
     *                                                 JSON decoding and validation logic
     * @param  string                 $testFile        Absolute path to the JSON test file containing test groups
     *                                                 and test cases to execute
     * @param  null|ProgressService   $progressService Optional service for displaying real-time progress
     *                                                 as each test executes
     * @return array<int, TestResult> Array of test results for all test cases in the file,
     *                                or empty array if file cannot be read or parsed
     */
    public function runTestFile(PrismTestInterface $prism, string $testFile, ?ProgressService $progressService = null): array
    {
        try {
            $fileContents = file_get_contents($testFile);
        } catch (Throwable) {
            return [];
        }

        if ($fileContents === false) {
            return [];
        }

        $testGroups = $prism->decodeJson($fileContents);

        if (!is_array($testGroups)) {
            return [];
        }

        $results = [];
        $relativePath = str_replace($prism->getTestDirectory().'/', '', $testFile);

        foreach ($testGroups as $groupIndex => $group) {
            if (!is_array($group)) {
                continue;
            }

            $schema = $group['schema'] ?? null;
            $groupDescription = is_string($group['description'] ?? null) ? $group['description'] : 'Unknown group';

            $tests = $group['tests'] ?? [];

            if (!is_array($tests)) {
                continue;
            }

            foreach ($tests as $testIndex => $test) {
                if (!is_array($test)) {
                    continue;
                }

                $data = $test['data'] ?? null;
                $expected = is_bool($test['valid'] ?? null) && $test['valid'];
                $testDescription = is_string($test['description'] ?? null) ? $test['description'] : 'Unknown test';

                /** @var array<int, string> $tags */
                $tags = $this->extractTags($test['tags'] ?? null);
                $assertionName = is_string($test['assertion'] ?? null) ? $test['assertion'] : null;

                $id = sprintf(
                    '%s:%s:%d:%d',
                    $prism->getName(),
                    basename($testFile, '.json'),
                    (int) $groupIndex,
                    (int) $testIndex,
                );

                $testStartTime = microtime(true);

                try {
                    $result = $prism->validate($data, $schema);
                    $actual = $result->isValid();
                    $testDuration = microtime(true) - $testStartTime;

                    // Use custom assertion if service is available
                    if ($this->assertionService instanceof CustomAssertionService) {
                        $assertionResult = $this->assertionService->execute(
                            $assertionName,
                            $data,
                            $expected,
                            $actual,
                        );

                        if ($assertionResult['passed']) {
                            $result = TestResult::pass(
                                id: $id,
                                file: $relativePath,
                                group: $groupDescription,
                                description: $testDescription,
                                data: $data,
                                expected: $expected,
                                duration: $testDuration,
                                tags: $tags,
                            );
                            $results[] = $result;
                            $progressService?->advance($result);
                        } else {
                            $result = TestResult::fail(
                                id: $id,
                                file: $relativePath,
                                group: $groupDescription,
                                description: $testDescription,
                                data: $data,
                                expected: $expected,
                                actual: $actual,
                                error: $assertionResult['message'],
                                duration: $testDuration,
                                tags: $tags,
                            );
                            $results[] = $result;
                            $progressService?->advance($result);
                        }
                    } elseif ($actual === $expected) {
                        // Default strict equality check
                        $result = TestResult::pass(
                            id: $id,
                            file: $relativePath,
                            group: $groupDescription,
                            description: $testDescription,
                            data: $data,
                            expected: $expected,
                            duration: $testDuration,
                            tags: $tags,
                        );
                        $results[] = $result;
                        $progressService?->advance($result);
                    } else {
                        $result = TestResult::fail(
                            id: $id,
                            file: $relativePath,
                            group: $groupDescription,
                            description: $testDescription,
                            data: $data,
                            expected: $expected,
                            actual: $actual,
                            duration: $testDuration,
                            tags: $tags,
                        );
                        $results[] = $result;
                        $progressService?->advance($result);
                    }
                } catch (Throwable $e) {
                    $testDuration = microtime(true) - $testStartTime;

                    $result = TestResult::fail(
                        id: $id,
                        file: $relativePath,
                        group: $groupDescription,
                        description: $testDescription,
                        data: $data,
                        expected: $expected,
                        actual: false,
                        error: $e->getMessage(),
                        duration: $testDuration,
                        tags: $tags,
                    );
                    $results[] = $result;
                    $progressService?->advance($result);
                }
            }
        }

        // Apply filter service test filtering
        if ($this->filterService instanceof FilterService) {
            return $this->filterService->filterResults($results);
        }

        return $results;
    }

    /**
     * Count total number of tests across multiple test files.
     *
     * Quickly parses JSON test files to count test cases without executing
     * validation logic. Used for initializing progress indicators with accurate
     * total counts before test execution begins.
     *
     * @param  PrismTestInterface $prism     The prism test instance for JSON decoding
     * @param  array<int, string> $testFiles Array of absolute paths to test files
     * @return int                Total number of test cases across all files
     */
    public function countTests(PrismTestInterface $prism, array $testFiles): int
    {
        $totalTests = 0;

        foreach ($testFiles as $testFile) {
            try {
                $fileContents = file_get_contents($testFile);
            } catch (Throwable) {
                continue;
            }

            if ($fileContents === false) {
                continue;
            }

            $testGroups = $prism->decodeJson($fileContents);

            if (!is_array($testGroups)) {
                continue;
            }

            foreach ($testGroups as $group) {
                if (!is_array($group)) {
                    continue;
                }

                $tests = $group['tests'] ?? [];

                if (!is_array($tests)) {
                    continue;
                }

                $totalTests += count($tests);
            }
        }

        return $totalTests;
    }

    /**
     * Extract and validate tags from test data.
     *
     * Ensures the tags array contains only string values with integer keys,
     * filtering out any non-string values that may exist in the test data.
     *
     * @param  mixed              $tags Raw tags value from test data
     * @return array<int, string> Validated array of string tags
     */
    private function extractTags(mixed $tags): array
    {
        if (!is_array($tags)) {
            return [];
        }

        /** @var array<int, string> $validTags */
        $validTags = [];

        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                continue;
            }

            $validTags[] = $tag;
        }

        return $validTags;
    }
}
