<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\Output;

use Cline\Compliance\ValueObjects\TestSuite;

use function array_map;
use function array_sum;
use function max;
use function mb_strlen;
use function number_format;
use function sprintf;
use function str_repeat;
use function Termwind\render;

/**
 * Renders test suite summaries using Termwind terminal formatting.
 *
 * Provides rich, colorized summary output for test results in interactive
 * terminal environments. Uses Laravel RouteListCommand-style dotted alignment
 * for consistent formatting, color-coded pass rates (green for 100%, yellow
 * for 95%+, red for below 95%), and a success banner for perfect compliance.
 *
 * @psalm-immutable
 */
final readonly class SummaryRenderer
{
    /**
     * Render summary output for all test suites.
     *
     * Displays individual suite summaries with pass/fail statistics followed
     * by an overall summary section. Uses color-coded formatting to highlight
     * pass rates and overall test results.
     *
     * @param array<int, TestSuite> $suites Array of test suites to render with results
     */
    public function render(array $suites): void
    {
        foreach ($suites as $suite) {
            $this->renderSuite($suite);
        }

        $this->renderSummary($suites);
    }

    /**
     * Render a single test suite summary line with dotted alignment.
     *
     * Outputs a formatted line showing suite name, pass/fail counts, and
     * color-coded pass rate percentage. Uses dots for visual alignment similar
     * to Laravel's RouteListCommand output style.
     *
     * @param TestSuite $suite The test suite to render
     */
    private function renderSuite(TestSuite $suite): void
    {
        $passRateColor = match (true) {
            $suite->passRate() === 100.0 => 'text-green',
            $suite->passRate() >= 95.0 => 'text-yellow',
            default => 'text-red',
        };

        // Calculate dots for alignment (Laravel RouteListCommand style)
        $label = $suite->name;
        $stats = sprintf('%d/%d tests', $suite->passedTests(), $suite->totalTests());
        $totalWidth = 60;
        $dotsNeeded = max(1, $totalWidth - mb_strlen($label) - mb_strlen($stats) - 1);
        $dots = str_repeat('.', $dotsNeeded);

        render(sprintf(
            <<<'HTML'
                <div class="mx-2">
                    <span class="text-white font-bold">%s</span> <span class="text-gray">%s</span>
                    <span class="ml-1 text-cyan">%4d</span><span class="text-gray">/</span><span class="text-white">%-4d tests</span>
                    <span class="ml-1 %s font-bold">(%.1f%%)</span>
                </div>
            HTML,
            $label,
            $dots,
            $suite->passedTests(),
            $suite->totalTests(),
            $passRateColor,
            $suite->passRate(),
        ));
    }

    /**
     * Render overall summary for all test suites.
     *
     * Displays either a success banner for 100% compliance or a detailed
     * breakdown showing total tests, passed/failed counts, and execution
     * duration. Uses dotted alignment and color coding for visual clarity.
     *
     * @param array<int, TestSuite> $suites Array of all test suites to summarize
     */
    private function renderSummary(array $suites): void
    {
        $totalTests = array_sum(array_map(fn (TestSuite $s): int => $s->totalTests(), $suites));
        $totalPassed = array_sum(array_map(fn (TestSuite $s): int => $s->passedTests(), $suites));
        $totalFailed = array_sum(array_map(fn (TestSuite $s): int => $s->failedTests(), $suites));
        $totalDuration = array_sum(array_map(fn (TestSuite $s): float => $s->duration, $suites));

        $allPassed = $totalFailed === 0;

        if ($allPassed) {
            render(<<<'HTML'
                <div class="mx-2 px-2 py-1 bg-green">
                    <span class="font-bold text-white">🎉 ALL TESTS PASSED - 100% COMPLIANCE</span>
                </div>
            HTML);
        } else {
            // Summary with dots alignment
            $summaryItems = [
                ['label' => 'Total', 'value' => number_format($totalTests).' tests', 'color' => 'text-white'],
                ['label' => 'Passed', 'value' => number_format($totalPassed).' tests', 'color' => 'text-green'],
                ['label' => 'Failed', 'value' => number_format($totalFailed).' tests', 'color' => 'text-red'],
                ['label' => 'Duration', 'value' => sprintf('%.2fs', $totalDuration), 'color' => 'text-cyan'],
            ];

            $totalWidth = 60;

            foreach ($summaryItems as $item) {
                $dotsNeeded = $totalWidth - mb_strlen($item['label']) - mb_strlen($item['value']) - 1;
                $dots = ' '.str_repeat('.', max(1, $dotsNeeded));

                render(sprintf(
                    <<<'HTML'
                        <div class="mx-2">
                            <span class="text-gray">%s%s</span>
                            <span class="ml-1 %s">%s</span>
                        </div>
                    HTML,
                    $item['label'],
                    $dots,
                    $item['color'],
                    $item['value'],
                ));
            }
        }
    }
}
