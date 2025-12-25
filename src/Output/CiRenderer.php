<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\Output;

use Cline\Compliance\ValueObjects\TestResult;
use Cline\Compliance\ValueObjects\TestSuite;
use Symfony\Component\Console\Output\OutputInterface;

use function array_map;
use function array_sum;
use function json_encode;
use function sprintf;
use function str_repeat;

/**
 * Renders compliance test results in CI-friendly plain text format.
 *
 * Outputs test suite results without terminal-specific formatting (no Termwind),
 * making it suitable for continuous integration environments, log files, and
 * non-interactive terminals. Uses box-drawing characters for visual structure
 * while maintaining compatibility with standard text output.
 *
 * @psalm-immutable
 */
final readonly class CiRenderer
{
    /**
     * Create a new CI renderer instance.
     *
     * @param OutputInterface $output The Symfony Console output interface for writing
     *                                formatted test results to the console or CI log
     */
    public function __construct(
        private OutputInterface $output,
    ) {}

    /**
     * Render test suite results in CI-friendly format.
     *
     * Outputs a formatted report with a header, individual suite summaries,
     * and an overall summary. Each suite displays pass/fail status, test counts,
     * and pass rate percentage.
     *
     * @param array<int, TestSuite> $suites Array of test suites to render with results
     */
    public function render(array $suites): void
    {
        $this->output->writeln('');
        $this->output->writeln('╔══════════════════════════════════════════════════════════════════╗');
        $this->output->writeln('║          Compliance Test Suite                                   ║');
        $this->output->writeln('╚══════════════════════════════════════════════════════════════════╝');
        $this->output->writeln('');

        foreach ($suites as $suite) {
            $this->renderSuite($suite);
        }

        $this->output->writeln('');
        $this->renderSummary($suites);
        $this->output->writeln('');
    }

    /**
     * Render detailed failure information for a test suite.
     *
     * Outputs all failed tests from the suite with detailed information including
     * test file, group name, description, expected vs actual validation results,
     * error messages, and test data. Skips rendering if the suite has no failures.
     *
     * @param TestSuite $suite The test suite containing failed tests to display
     */
    public function renderFailures(TestSuite $suite): void
    {
        $failures = $suite->failures();

        if ($failures === []) {
            return;
        }

        $this->output->writeln('');
        $this->output->writeln(sprintf('Failures for %s:', $suite->name));
        $this->output->writeln(str_repeat('-', 80));

        foreach ($failures as $index => $failure) {
            $this->renderFailure($index + 1, $failure);
        }
    }

    /**
     * Render a single test suite summary line.
     *
     * Outputs a compact summary showing suite name, pass/fail indicator,
     * test counts (passed/total), and pass rate percentage.
     *
     * @param TestSuite $suite The test suite to render
     */
    private function renderSuite(TestSuite $suite): void
    {
        $status = $suite->failedTests() === 0 ? '✓' : '✗';

        $this->output->writeln(sprintf(
            '%s %-15s  %4d/%4d tests  (%5.1f%%)',
            $status,
            $suite->name.':',
            $suite->passedTests(),
            $suite->totalTests(),
            $suite->passRate(),
        ));
    }

    /**
     * Render overall summary for all test suites.
     *
     * Displays summary statistics including total tests, passed/failed counts,
     * and execution duration. Shows a success banner if all tests passed.
     *
     * @param array<int, TestSuite> $suites Array of all test suites to summarize
     */
    private function renderSummary(array $suites): void
    {
        $totalTests = array_sum(array_map(fn (TestSuite $s): int => $s->totalTests(), $suites));
        $totalPassed = array_sum(array_map(fn (TestSuite $s): int => $s->passedTests(), $suites));
        $totalFailed = array_sum(array_map(fn (TestSuite $s): int => $s->failedTests(), $suites));
        $totalDuration = array_sum(array_map(fn (TestSuite $s): float => $s->duration, $suites));

        $passedPercentage = $totalTests > 0 ? ($totalPassed / $totalTests) * 100 : 0;
        $failedPercentage = $totalTests > 0 ? ($totalFailed / $totalTests) * 100 : 0;

        $this->output->writeln(sprintf('Total:    %6d tests', $totalTests));
        $this->output->writeln(sprintf('Passed:   %6d tests  (%5.1f%%)', $totalPassed, $passedPercentage));
        $this->output->writeln(sprintf('Failed:   %6d tests  (%5.1f%%)', $totalFailed, $failedPercentage));
        $this->output->writeln(sprintf('Duration: %6.2fs', $totalDuration));
    }

    /**
     * Render detailed information for a single failed test.
     *
     * Outputs file path, test group, description, expected vs actual validation
     * results, optional error message, and test data in JSON format.
     *
     * @param int        $number  The sequential failure number for display
     * @param TestResult $failure The failed test result to render
     */
    private function renderFailure(int $number, TestResult $failure): void
    {
        $this->output->writeln(sprintf('%d. %s', $number, $failure->file));
        $this->output->writeln(sprintf('   Group: %s', $failure->group));
        $this->output->writeln(sprintf('   Test: %s', $failure->description));
        $this->output->writeln(sprintf(
            '   Expected: %s, Got: %s',
            $failure->expectedValid ? 'VALID' : 'INVALID',
            $failure->actualValid ? 'VALID' : 'INVALID',
        ));

        if ($failure->error !== null) {
            $this->output->writeln(sprintf('   Error: %s', $failure->error));
        }

        $this->output->writeln(sprintf('   Data: %s', json_encode($failure->data)));
        $this->output->writeln(str_repeat('-', 80));
    }
}
