<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Output;

use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;

use const STR_PAD_LEFT;

use function array_merge;
use function array_slice;
use function count;
use function max;
use function mb_str_pad;
use function mb_strlen;
use function number_format;
use function sprintf;
use function usort;

/**
 * Renders performance profiling information for test suites.
 *
 * Displays the slowest tests across all test suites, helping identify
 * performance bottlenecks and slow validation operations. Shows execution
 * time and test details in a formatted table, sorted by duration in
 * descending order.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ProfileRenderer
{
    /**
     * Render profile report showing slowest tests.
     *
     * Collects all test results from all suites, sorts them by duration
     * (slowest first), and displays the top N slowest tests with their
     * execution times in milliseconds and test IDs. Returns a warning
     * message if no tests are available to profile.
     *
     * @param array<int, TestSuite> $suites Test suites to profile with all test
     *                                      results and their execution durations
     * @param int                   $limit  Maximum number of slow tests to display
     *                                      in the profiling report (default: 10)
     *
     * @return string Formatted profile report with slowest tests and durations
     */
    public function render(array $suites, int $limit = 10): string
    {
        // Collect all test results from all suites
        $allResults = [];

        foreach ($suites as $suite) {
            $allResults = array_merge($allResults, $suite->results);
        }

        if ($allResults === []) {
            return "\n<fg=yellow>No tests to profile.</>\n";
        }

        // Sort by duration descending
        usort($allResults, fn (TestResult $a, TestResult $b): int => $b->duration <=> $a->duration);

        // Take top N slowest tests
        $slowestTests = array_slice($allResults, 0, $limit);

        if ($slowestTests === []) {
            return "\n<fg=yellow>No slow tests found.</>\n";
        }

        // Calculate column widths
        $maxDurationWidth = 0;

        foreach ($slowestTests as $test) {
            $durationStr = number_format($test->duration * 1_000, 2);
            $maxDurationWidth = max($maxDurationWidth, mb_strlen($durationStr));
        }

        // Build output
        $output = "\n<fg=cyan;options=bold>Performance Profile - Top ".count($slowestTests)." Slowest Tests</>\n\n";

        foreach ($slowestTests as $test) {
            $durationMs = number_format($test->duration * 1_000, 2);
            $durationStr = mb_str_pad($durationMs, $maxDurationWidth, ' ', STR_PAD_LEFT);

            $output .= sprintf(
                "  <fg=yellow>%sms</> %s\n",
                $durationStr,
                $test->id,
            );
        }

        return $output."\n";
    }
}
