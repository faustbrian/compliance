<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Output;

use Cline\Prism\ValueObjects\TestSuite;

use function array_map;
use function array_sum;
use function is_array;
use function is_float;
use function is_int;
use function sprintf;

/**
 * Renders benchmark comparison results showing performance changes.
 *
 * Compares current test suite performance against a stored baseline,
 * highlighting improvements and regressions in execution time. Displays
 * per-suite comparisons with percentage changes and an overall summary
 * with color-coded indicators for performance trends.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class BenchmarkRenderer
{
    /**
     * Render benchmark comparison report.
     *
     * Creates a detailed comparison report showing current vs baseline execution
     * times for each test suite. Calculates time differences and percentage changes,
     * using color coding (green for improvements, red for regressions) to highlight
     * performance trends. Includes an overall summary across all suites.
     *
     * @param array<int, TestSuite> $suites       Collection of current test suite results
     *                                            to compare against baseline performance
     * @param array<string, mixed>  $baselineData Stored baseline performance data containing
     *                                            suite names as keys and total_duration values
     *                                            for historical comparison reference
     *
     * @return string Formatted benchmark comparison report with per-suite and overall statistics
     */
    public function render(array $suites, array $baselineData): string
    {
        $output = "\n<fg=cyan;options=bold>Benchmark Comparison</>\n\n";

        $totalCurrentDuration = array_sum(array_map(fn (TestSuite $s): float => $s->duration, $suites));
        $totalBaselineDuration = 0.0;

        foreach ($baselineData as $suiteData) {
            if (!is_array($suiteData)) {
                continue;
            }

            $duration = $suiteData['total_duration'] ?? null;

            if (!is_float($duration) && !is_int($duration)) {
                continue;
            }

            $totalBaselineDuration += (float) $duration;
        }

        foreach ($suites as $suite) {
            if (!isset($baselineData[$suite->name])) {
                continue;
            }

            $baseline = $baselineData[$suite->name];

            if (!is_array($baseline)) {
                continue;
            }

            $duration = $baseline['total_duration'] ?? null;

            if (!is_float($duration) && !is_int($duration)) {
                continue;
            }

            $baselineDuration = (float) $duration;
            $currentDuration = $suite->duration;

            $difference = $currentDuration - $baselineDuration;
            $percentChange = $baselineDuration > 0 ? ($difference / $baselineDuration) * 100 : 0;

            $color = $difference < 0 ? 'green' : ($difference > 0 ? 'red' : 'white');
            $sign = $difference > 0 ? '+' : '';

            $output .= sprintf(
                "  <fg=white>%s:</>\n",
                $suite->name,
            );

            $output .= sprintf(
                "    Current: %.3fs | Baseline: %.3fs | <fg=%s>%s%.3fs (%s%.1f%%)</>\n",
                $currentDuration,
                $baselineDuration,
                $color,
                $sign,
                $difference,
                $sign,
                $percentChange,
            );
        }

        // Overall summary
        $totalDifference = $totalCurrentDuration - $totalBaselineDuration;
        $totalPercentChange = $totalBaselineDuration > 0 ? ($totalDifference / $totalBaselineDuration) * 100 : 0;
        $totalColor = $totalDifference < 0 ? 'green' : ($totalDifference > 0 ? 'red' : 'white');
        $totalSign = $totalDifference > 0 ? '+' : '';

        $output .= sprintf(
            "\n  <fg=white;options=bold>Overall:</>\n    Current: %.3fs | Baseline: %.3fs | <fg=%s;options=bold>%s%.3fs (%s%.1f%%)</>\n",
            $totalCurrentDuration,
            $totalBaselineDuration,
            $totalColor,
            $totalSign,
            $totalDifference,
            $totalSign,
            $totalPercentChange,
        );

        if ($totalDifference < 0) {
            $output .= "\n  <fg=green;options=bold>✓ Performance improved!</>\n";
        } elseif ($totalDifference > 0) {
            $output .= "\n  <fg=red;options=bold>⚠ Performance regressed!</>\n";
        } else {
            $output .= "\n  <fg=white;options=bold>- No performance change</>\n";
        }

        return $output."\n";
    }
}
