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
use function count;
use function json_encode;
use function sprintf;
use function str_repeat;

/**
 * @psalm-immutable
 */
final readonly class CiRenderer
{
    public function __construct(
        private OutputInterface $output,
    ) {}

    /**
     * @param array<int, TestSuite> $suites
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

    public function renderFailures(TestSuite $suite): void
    {
        $failures = $suite->failures();

        if (count($failures) === 0) {
            return;
        }

        $this->output->writeln('');
        $this->output->writeln(sprintf('Failures for %s:', $suite->name));
        $this->output->writeln(str_repeat('-', 80));

        foreach ($failures as $index => $failure) {
            $this->renderFailure($index + 1, $failure);
        }
    }

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
     * @param array<int, TestSuite> $suites
     */
    private function renderSummary(array $suites): void
    {
        $totalFailed = array_sum(array_map(fn (TestSuite $s) => $s->failedTests(), $suites));

        if ($totalFailed === 0) {
            $this->output->writeln('╔══════════════════════════════════════════════════════════════════╗');
            $this->output->writeln('║  🎉 ALL TESTS PASSED - 100% COMPLIANCE                           ║');
            $this->output->writeln('╚══════════════════════════════════════════════════════════════════╝');
        } else {
            $this->output->writeln('Some tests have failures.');
        }
    }

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
