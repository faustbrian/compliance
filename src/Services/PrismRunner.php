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
 */
final readonly class PrismRunner
{
    /**
     * Execute a prism test suite and return aggregated results.
     *
     * Discovers all JSON test files in the prism test directory, runs each
     * test case by validating data against schemas, and aggregates the results
     * with total execution time. Test files are processed in sorted order to
     * ensure consistent execution across runs.
     *
     * @param  PrismTestInterface $prism The prism test instance defining
     *                                   the test directory, validation logic,
     *                                   and JSON decoding behavior for test files
     * @return TestSuite          Complete test suite containing all test results, metadata,
     *                            and execution duration in seconds
     */
    public function run(PrismTestInterface $prism): TestSuite
    {
        $startTime = microtime(true);
        $results = [];

        $testFiles = $this->collectTestFiles($prism);

        foreach ($testFiles as $testFile) {
            $fileResults = $this->runTestFile($prism, $testFile);
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
    private function collectTestFiles(PrismTestInterface $prism): array
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
     * @param  PrismTestInterface     $prism    The prism test instance providing
     *                                          JSON decoding and validation logic
     * @param  string                 $testFile Absolute path to the JSON test file containing test groups
     *                                          and test cases to execute
     * @return array<int, TestResult> Array of test results for all test cases in the file,
     *                                or empty array if file cannot be read or parsed
     */
    private function runTestFile(PrismTestInterface $prism, string $testFile): array
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
                $expectedValid = is_bool($test['valid'] ?? null) && $test['valid'];
                $testDescription = is_string($test['description'] ?? null) ? $test['description'] : 'Unknown test';

                $id = sprintf(
                    '%s:%s:%d:%d',
                    $prism->getName(),
                    basename($testFile, '.json'),
                    (int) $groupIndex,
                    (int) $testIndex,
                );

                try {
                    $result = $prism->validate($data, $schema);
                    $actualValid = $result->isValid();

                    if ($actualValid === $expectedValid) {
                        $results[] = TestResult::pass(
                            id: $id,
                            file: $relativePath,
                            group: $groupDescription,
                            description: $testDescription,
                            data: $data,
                            expectedValid: $expectedValid,
                        );
                    } else {
                        $results[] = TestResult::fail(
                            id: $id,
                            file: $relativePath,
                            group: $groupDescription,
                            description: $testDescription,
                            data: $data,
                            expectedValid: $expectedValid,
                            actualValid: $actualValid,
                        );
                    }
                } catch (Throwable $e) {
                    $results[] = TestResult::fail(
                        id: $id,
                        file: $relativePath,
                        group: $groupDescription,
                        description: $testDescription,
                        data: $data,
                        expectedValid: $expectedValid,
                        actualValid: false,
                        error: $e->getMessage(),
                    );
                }
            }
        }

        return $results;
    }
}
