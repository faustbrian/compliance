<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use Cline\Prism\ValueObjects\TestResult;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

use function microtime;
use function sprintf;

/**
 * Service for displaying real-time test execution progress.
 *
 * Provides progress bars for normal mode and detailed test-by-test
 * output for verbose mode, giving users immediate feedback during
 * test execution rather than waiting for completion.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ProgressService
{
    private ?ProgressBar $progressBar = null;

    private int $passedCount = 0;

    private int $failedCount = 0;

    private float $startTime = 0.0;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly bool $verbose = false,
    ) {}

    /**
     * Start progress tracking for a test suite.
     */
    public function start(int $totalTests): void
    {
        $this->startTime = microtime(true);
        $this->passedCount = 0;
        $this->failedCount = 0;

        if ($this->verbose) {
            $this->output->writeln(sprintf('<fg=cyan>Running %d tests...</>', $totalTests));
            $this->output->writeln('');

            return;
        }

        // Create progress bar for non-verbose mode
        $this->progressBar = new ProgressBar($this->output, $totalTests);
        $this->progressBar->setFormat(
            '%current%/%max% [%bar%] %percent:3s%% - %elapsed:6s% - %message%',
        );
        $this->progressBar->setMessage('Starting...');
        $this->progressBar->start();
    }

    /**
     * Update progress after a test completes.
     */
    public function advance(TestResult $result): void
    {
        if ($result->passed) {
            ++$this->passedCount;
        } else {
            ++$this->failedCount;
        }

        if ($this->verbose) {
            $this->outputVerboseResult($result);

            return;
        }

        if (!$this->progressBar instanceof ProgressBar) {
            return;
        }

        $this->progressBar->setMessage(sprintf(
            '%d passed, %d failed',
            $this->passedCount,
            $this->failedCount,
        ));
        $this->progressBar->advance();
    }

    /**
     * Finish progress tracking.
     */
    public function finish(): void
    {
        if ($this->verbose) {
            $duration = microtime(true) - $this->startTime;
            $this->output->writeln('');
            $this->output->writeln(sprintf(
                '<fg=cyan>Completed in %.2fs - %d passed, %d failed</>',
                $duration,
                $this->passedCount,
                $this->failedCount,
            ));
            $this->output->writeln('');

            return;
        }

        if (!$this->progressBar instanceof ProgressBar) {
            return;
        }

        $this->progressBar->finish();
        $this->output->writeln('');
        $this->output->writeln('');
    }

    /**
     * Output detailed result for verbose mode.
     */
    private function outputVerboseResult(TestResult $result): void
    {
        $icon = $result->passed ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $time = sprintf('%.3fs', $result->duration);

        $this->output->writeln(sprintf(
            '%s %s - %s (%s)',
            $icon,
            $result->group,
            $result->description,
            $time,
        ));

        if ($result->passed || $result->error === null) {
            return;
        }

        $this->output->writeln(sprintf('  <fg=red>%s</>', $result->error));
    }
}
