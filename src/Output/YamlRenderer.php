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
use Symfony\Component\Yaml\Yaml;

use function array_map;
use function array_sum;

/**
 * Renders compliance test results in YAML format.
 *
 * Produces structured YAML output suitable for programmatic consumption,
 * configuration management, and human-readable structured data. Includes
 * summary statistics and optionally detailed failure information.
 */
final readonly class YamlRenderer
{
    /**
     * Create a new YAML renderer instance.
     *
     * @param OutputInterface $output        The Symfony Console output interface for writing
     * @param bool            $includeFailures Whether to include detailed failure information in output
     */
    public function __construct(
        private OutputInterface $output,
        private bool $includeFailures = false,
    ) {}

    /**
     * Render test suites as YAML.
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
            'suites' => array_map(fn (TestSuite $s): array => $this->formatSuite($s), $suites),
        ];

        $yaml = Yaml::dump($data, 10, 2);
        $this->output->writeln($yaml);
    }

    /**
     * Format a test suite for YAML output.
     *
     * @param  TestSuite $suite Test suite to format
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
