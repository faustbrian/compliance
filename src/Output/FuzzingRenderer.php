<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Output;

use Cline\Prism\ValueObjects\TestSuite;

use function sprintf;

/**
 * Renders fuzzing test results showing generated test coverage.
 *
 * Displays results from automatically generated fuzz tests including
 * edge cases and random data, highlighting discovered errors. Shows
 * total test counts, pass/fail statistics, and detailed failure information
 * for any tests that failed during fuzzing.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class FuzzingRenderer
{
    /**
     * Render fuzzing test results.
     *
     * Creates a formatted report showing fuzzing test statistics including
     * total tests, pass/fail counts, and pass rate. Lists all failures with
     * their descriptions, error messages, and execution durations. Shows a
     * success message if all fuzzed tests passed.
     *
     * @param TestSuite $suite Fuzzing test suite results containing all generated
     *                         tests and their outcomes including pass/fail status
     *                         and any errors discovered during fuzzing execution
     *
     * @return string Formatted fuzzing results report with statistics and failure details
     */
    public function render(TestSuite $suite): string
    {
        $output = "\n<fg=cyan;options=bold>Fuzzing Test Results</>\n\n";

        $total = $suite->totalTests();
        $passed = $suite->passedTests();
        $failed = $suite->failedTests();

        $output .= sprintf("Total Fuzzed Tests: %d\n", $total);
        $output .= sprintf("Passed: <fg=green>%d</>\n", $passed);
        $output .= sprintf("Failed: <fg=red>%d</>\n", $failed);

        if ($failed > 0) {
            $passRate = ($passed / $total) * 100;
            $output .= sprintf("Pass Rate: %.1f%%\n\n", $passRate);

            $output .= sprintf("<fg=yellow>Found %d failure(s) during fuzzing:</>\n\n", $failed);

            $failedResults = [];

            foreach ($suite->results as $result) {
                if ($result->passed) {
                    continue;
                }

                $failedResults[] = $result;
            }

            foreach ($failedResults as $index => $result) {
                $output .= sprintf("<fg=yellow>%d. %s</>\n", $index + 1, $result->id);
                $output .= sprintf("   Description: %s\n", $result->description);

                if ($result->error !== null) {
                    $output .= sprintf("   Error: <fg=red>%s</>\n", $result->error);
                }

                $output .= sprintf("   Duration: %.4fs\n", $result->duration);
                $output .= "\n";
            }
        } else {
            $output .= "\n<fg=green>✓ All fuzzed tests passed!</>\n";
            $output .= "\nNo unexpected errors or validation failures discovered.\n";
        }

        return $output;
    }
}
