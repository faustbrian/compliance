<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use Cline\Prism\ValueObjects\TestSuite;

use function array_unique;
use function arsort;
use function count;
use function min;

/**
 * Service for analyzing test coverage and identifying gaps in test suites.
 *
 * Tracks which test groups, files, and tags are covered by tests, helping
 * identify untested areas and measure overall test suite completeness.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class CoverageService
{
    /**
     * Analyze test coverage across test suites.
     *
     * @param array<int, TestSuite> $suites Test suites to analyze
     * @return array{
     *     total_tests: int,
     *     passed_tests: int,
     *     failed_tests: int,
     *     pass_rate: float,
     *     groups: array{count: int, distribution: array<string, int>},
     *     files: array{count: int, distribution: array<string, int>},
     *     tags: array{count: int, distribution: array<string, int>},
     *     coverage_score: float
     * }  Coverage analysis data
     */
    public function analyze(array $suites): array
    {
        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;
        $groups = [];
        $files = [];
        $tags = [];

        foreach ($suites as $suite) {
            $totalTests += $suite->totalTests();
            $passedTests += $suite->passedTests();
            $failedTests += $suite->failedTests();

            foreach ($suite->results as $result) {
                // Track unique groups
                $groups[$result->group] = ($groups[$result->group] ?? 0) + 1;

                // Track unique files
                $files[$result->file] = ($files[$result->file] ?? 0) + 1;

                // Track unique tags
                foreach ($result->tags as $tag) {
                    $tags[$tag] = ($tags[$tag] ?? 0) + 1;
                }
            }
        }

        return [
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'failed_tests' => $failedTests,
            'pass_rate' => $totalTests > 0 ? (float) (($passedTests / $totalTests) * 100) : 0.0,
            'groups' => [
                'count' => count($groups),
                'distribution' => $this->sortByCount($groups),
            ],
            'files' => [
                'count' => count($files),
                'distribution' => $this->sortByCount($files),
            ],
            'tags' => [
                'count' => count($tags),
                'distribution' => $this->sortByCount($tags),
            ],
            'coverage_score' => $this->calculateCoverageScore($suites),
        ];
    }

    /**
     * Sort array by count descending.
     *
     * @param  array<string, int> $data Data to sort
     * @return array<string, int> Sorted data
     */
    private function sortByCount(array $data): array
    {
        arsort($data);

        return $data;
    }

    /**
     * Calculate overall coverage score based on various metrics.
     *
     * @param  array<int, TestSuite> $suites Test suites
     * @return float                 Coverage score (0-100)
     */
    private function calculateCoverageScore(array $suites): float
    {
        if ($suites === []) {
            return 0.0;
        }

        $totalTests = 0;
        $passedTests = 0;
        $uniqueGroups = [];
        $uniqueFiles = [];

        foreach ($suites as $suite) {
            $totalTests += $suite->totalTests();
            $passedTests += $suite->passedTests();

            foreach ($suite->results as $result) {
                $uniqueGroups[] = $result->group;
                $uniqueFiles[] = $result->file;
            }
        }

        $passRate = $totalTests > 0 ? ($passedTests / $totalTests) : 0;
        $groupDiversity = count(array_unique($uniqueGroups));
        $fileDiversity = count(array_unique($uniqueFiles));

        // Weight: 60% pass rate, 20% group diversity, 20% file diversity
        $score = ($passRate * 0.6) + (($groupDiversity / 10) * 0.2) + (($fileDiversity / 10) * 0.2);

        return min(100.0, $score * 100);
    }
}
