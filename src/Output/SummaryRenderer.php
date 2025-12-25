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

        // Summary with dots alignment - match suite rendering style
        $passedPercentage = $totalTests > 0 ? ($totalPassed / $totalTests) * 100 : 0;
        $failedPercentage = $totalTests > 0 ? ($totalFailed / $totalTests) * 100 : 0;

        $totalFormatted = number_format($totalTests);
        $passedFormatted = number_format($totalPassed);
        $failedFormatted = number_format($totalFailed);

        $totalWidth = 60;

        // Render Total
        $totalStats = $totalFormatted.' tests';
        $dotsNeeded = max(1, $totalWidth - mb_strlen('Total') - mb_strlen($totalStats) - 1);
        $dots = str_repeat('.', $dotsNeeded);
        render(sprintf(
            <<<'HTML'
                <div class="mx-2">
                    <span class="text-cyan">Total</span> <span class="text-gray">%s</span>
                    <span class="ml-1 text-white">%s tests</span>
                </div>
            HTML,
            $dots,
            $totalFormatted,
        ));

        // Render Passed
        $passedStats = $passedFormatted.' tests';
        $dotsNeeded = max(1, $totalWidth - mb_strlen('Passed') - mb_strlen($passedStats) - 1);
        $dots = str_repeat('.', $dotsNeeded);
        render(sprintf(
            <<<'HTML'
                <div class="mx-2">
                    <span class="text-green">Passed</span> <span class="text-gray">%s</span>
                    <span class="ml-1 text-white">%s tests</span>
                    <span class="ml-1 text-green font-bold">(%.1f%%)</span>
                </div>
            HTML,
            $dots,
            $passedFormatted,
            $passedPercentage,
        ));

        // Render Failed
        $failedStats = $failedFormatted.' tests';
        $dotsNeeded = max(1, $totalWidth - mb_strlen('Failed') - mb_strlen($failedStats) - 1);
        $dots = str_repeat('.', $dotsNeeded);
        render(sprintf(
            <<<'HTML'
                <div class="mx-2">
                    <span class="text-red">Failed</span> <span class="text-gray">%s</span>
                    <span class="ml-1 text-white">%s tests</span>
                    <span class="ml-1 text-red font-bold">(%.1f%%)</span>
                </div>
            HTML,
            $dots,
            $failedFormatted,
            $failedPercentage,
        ));

        // Render Duration
        $durationStr = sprintf('%.2fs', $totalDuration);
        $dotsNeeded = max(1, $totalWidth - mb_strlen('Duration') - mb_strlen($durationStr) - 1);
        $dots = str_repeat('.', $dotsNeeded);
        render(sprintf(
            <<<'HTML'
                <div class="mx-2">
                    <span class="text-yellow">Duration</span> <span class="text-gray">%s</span>
                    <span class="ml-1 text-cyan">%s</span>
                </div>
            HTML,
            $dots,
            $durationStr,
        ));
    }
}
