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

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use function array_map;
use function array_sum;
use function json_encode;
use function round;

/**
 * Renders compliance test results in JSON format.
 *
 * Produces structured JSON output suitable for programmatic consumption,
 * CI/CD pipelines, and integration with other tools. Includes summary
 * statistics and optionally detailed failure information.
 * @psalm-immutable
 */
final readonly class JsonRenderer
{
    /**
     * Create a new JSON renderer instance.
     *
     * @param OutputInterface $output          The Symfony Console output interface for writing
     * @param bool            $includeFailures Whether to include detailed failure information in output
     */
    public function __construct(
        private OutputInterface $output,
        private bool $includeFailures = false,
    ) {}

    /**
     * Render test suites as JSON.
     *
     * @param array<int, TestSuite> $suites Collection of test suite results to render
     */
    public function render(array $suites): void
    {
        $totalTests = array_sum(array_map(fn (TestSuite $s): int => $s->totalTests(), $suites));
        $totalPassed = array_sum(array_map(fn (TestSuite $s): int => $s->passedTests(), $suites));
        $totalFailed = array_sum(array_map(fn (TestSuite $s): int => $s->failedTests(), $suites));
        $totalDuration = array_sum(array_map(fn (TestSuite $s): float => $s->duration, $suites));

        $data = [
            'summary' => [
                'total' => $totalTests,
                'passed' => $totalPassed,
                'failed' => $totalFailed,
                'pass_rate' => $totalTests > 0 ? round(($totalPassed / $totalTests) * 100, 1) : 0.0,
                'duration' => round($totalDuration, 2),
            ],
            'suites' => array_map($this->formatSuite(...), $suites),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->output->writeln($json);
    }

    /**
     * Format a test suite for JSON output.
     *
     * @param  TestSuite            $suite Test suite to format
     * @return array<string, mixed> Formatted suite data
     */
    private function formatSuite(TestSuite $suite): array
    {
        $data = [
            'name' => $suite->name,
            'total' => $suite->totalTests(),
            'passed' => $suite->passedTests(),
            'failed' => $suite->failedTests(),
            'pass_rate' => round($suite->passRate(), 1),
            'duration' => round($suite->duration, 2),
        ];

        if ($this->includeFailures && $suite->failedTests() > 0) {
            $data['failures'] = array_map(
                fn (TestResult $r): array => [
                    'id' => $r->id,
                    'file' => $r->file,
                    'group' => $r->group,
                    'description' => $r->description,
                    'expected_valid' => $r->expectedValid,
                    'actual_valid' => $r->actualValid,
                    'error' => $r->error,
                    'data' => $r->data,
                ],
                $suite->failures(),
            );
        }

        return $data;
    }
}
