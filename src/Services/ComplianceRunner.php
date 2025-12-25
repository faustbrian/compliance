<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\Services;

use Cline\Compliance\Contracts\ComplianceTestInterface;
use Cline\Compliance\ValueObjects\TestResult;
use Cline\Compliance\ValueObjects\TestSuite;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

use function basename;
use function file_get_contents;
use function is_array;
use function is_dir;
use function microtime;
use function sort;
use function sprintf;
use function str_replace;

/**
 * @psalm-immutable
 */
final readonly class ComplianceRunner
{
    public function run(ComplianceTestInterface $compliance): TestSuite
    {
        $startTime = microtime(true);
        $results = [];

        $testFiles = $this->collectTestFiles($compliance);

        foreach ($testFiles as $testFile) {
            $fileResults = $this->runTestFile($compliance, $testFile);
            $results = [...$results, ...$fileResults];
        }

        $duration = microtime(true) - $startTime;

        return new TestSuite(
            name: $compliance->getName(),
            results: $results,
            duration: $duration,
        );
    }

    /**
     * @return array<int, string>
     */
    private function collectTestFiles(ComplianceTestInterface $compliance): array
    {
        $testDir = $compliance->getTestDirectory();

        if (!is_dir($testDir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($testDir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $testFiles = [];

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $testFiles[] = $file->getPathname();
        }

        sort($testFiles);

        return $testFiles;
    }

    /**
     * @return array<int, TestResult>
     */
    private function runTestFile(ComplianceTestInterface $compliance, string $testFile): array
    {
        $fileContents = file_get_contents($testFile);

        if ($fileContents === false) {
            return [];
        }

        $testGroups = $compliance->decodeJson($fileContents);

        if (!is_array($testGroups)) {
            return [];
        }

        $results = [];
        $relativePath = str_replace($compliance->getTestDirectory().'/', '', $testFile);

        foreach ($testGroups as $groupIndex => $group) {
            $schema = $group['schema'] ?? null;
            $groupDescription = $group['description'] ?? 'Unknown group';

            foreach ($group['tests'] ?? [] as $testIndex => $test) {
                $data = $test['data'] ?? null;
                $expectedValid = $test['valid'] ?? false;
                $testDescription = $test['description'] ?? 'Unknown test';

                $id = sprintf(
                    '%s:%s:%d:%d',
                    $compliance->getName(),
                    basename($testFile, '.json'),
                    $groupIndex,
                    $testIndex,
                );

                try {
                    $result = $compliance->validate($data, $schema);
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
