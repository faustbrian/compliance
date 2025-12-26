<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Output;

use function implode;
use function sprintf;
use function Termwind\render;

/**
 * Renderer for displaying test coverage analysis reports.
 *
 * Shows comprehensive coverage metrics including total test counts,
 * pass/fail statistics, pass rate percentage, and distribution analysis
 * across groups, files, and tags. Provides a coverage score (0-100)
 * weighted by pass rate and diversity metrics.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class CoverageRenderer
{
    /**
     * Render a coverage analysis report to the terminal.
     *
     * Displays: coverage score, pass rate, total/passed/failed counts,
     * distribution of groups/files/tags with item counts and top entries.
     *
     * @param array{
     *     total_tests: int,
     *     passed_tests: int,
     *     failed_tests: int,
     *     pass_rate: float,
     *     groups: array{count: int, distribution: array<string, int>},
     *     files: array{count: int, distribution: array<string, int>},
     *     tags: array{count: int, distribution: array<string, int>},
     *     coverage_score: float
     * } $coverage Coverage analysis data from CoverageService
     */
    public function render(array $coverage): void
    {
        render(<<<HTML
            <div class="mx-2 my-1">
                <div class="mb-1">
                    <span class="text-green font-bold">Coverage Analysis</span>
                </div>

                <div class="mb-1">
                    <span class="font-bold">Coverage Score:</span>
                    <span class="text-yellow">{$coverage['coverage_score']}%</span>
                </div>

                <div class="mb-1">
                    <span class="font-bold">Total Tests:</span> {$coverage['total_tests']}
                    <span class="ml-2 font-bold">Passed:</span> <span class="text-green">{$coverage['passed_tests']}</span>
                    <span class="ml-2 font-bold">Failed:</span> <span class="text-red">{$coverage['failed_tests']}</span>
                    <span class="ml-2 font-bold">Pass Rate:</span> <span class="text-cyan">{$coverage['pass_rate']}%</span>
                </div>

                <div class="mb-1">
                    <span class="font-bold">Groups:</span> {$coverage['groups']['count']} unique
                </div>
                {$this->renderDistribution($coverage['groups']['distribution'])}

                <div class="mb-1 mt-1">
                    <span class="font-bold">Files:</span> {$coverage['files']['count']} unique
                </div>
                {$this->renderDistribution($coverage['files']['distribution'])}

                <div class="mb-1 mt-1">
                    <span class="font-bold">Tags:</span> {$coverage['tags']['count']} unique
                </div>
                {$this->renderDistribution($coverage['tags']['distribution'])}
            </div>
        HTML);
    }

    /**
     * Render distribution data for a category (groups/files/tags).
     *
     * @param array<string, int> $distribution Item names mapped to counts
     */
    private function renderDistribution(array $distribution): string
    {
        if ($distribution === []) {
            return '<div class="ml-4 text-gray">No data</div>';
        }

        $items = [];
        $count = 0;

        foreach ($distribution as $name => $testCount) {
            if ($count >= 5) {
                break;
            }

            $items[] = sprintf('<div class="ml-4"><span class="text-gray">•</span> %s: <span class="text-cyan">%d</span></div>', $name, $testCount);
            ++$count;
        }

        return implode("\n", $items);
    }
}
