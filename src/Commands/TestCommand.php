<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\Commands;

use Cline\Compliance\Output\CiRenderer;
use Cline\Compliance\Output\DetailRenderer;
use Cline\Compliance\Output\SummaryRenderer;
use Cline\Compliance\Services\ComplianceRunner;
use Cline\Compliance\Services\ConfigLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_map;
use function array_sum;
use function is_string;
use function sprintf;

/**
 * Console command for executing compliance validation tests.
 *
 * Loads compliance test configurations, runs validation tests against the
 * configured schemas, and renders results using either CI-friendly plain text
 * output or enhanced terminal output with Termwind. Supports filtering tests
 * by draft/version and displaying detailed failure information.
 */
final class TestCommand extends Command
{
    /**
     * Configure the command definition with options.
     *
     * Sets up the command name, description, and available options for controlling
     * output format (CI mode), failure details display, and draft-specific filtering.
     */
    protected function configure(): void
    {
        $this
            ->setName('test')
            ->setDescription('Run compliance tests')
            ->addOption('ci', null, InputOption::VALUE_NONE, 'Use CI-friendly output (no Termwind)')
            ->addOption('failures', null, InputOption::VALUE_NONE, 'Show detailed failure information')
            ->addOption('draft', null, InputOption::VALUE_REQUIRED, 'Run tests for specific draft only');
    }

    /**
     * Execute the compliance test command.
     *
     * Loads test configurations, optionally filters by draft name, executes all
     * matching compliance tests, and renders results using the appropriate output
     * renderer based on the --ci flag. Returns success only if all tests pass.
     *
     * @param  InputInterface  $input  Console input interface providing command options and arguments
     * @param  OutputInterface $output Console output interface for writing test results and messages
     * @return int             Command::SUCCESS (0) if all tests pass, Command::FAILURE (1) if any test fails or configuration errors occur
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configLoader = new ConfigLoader();
        $complianceTests = $configLoader->load();

        if ($complianceTests === []) {
            $output->writeln('<error>No compliance tests found. Create a compliance.php config file.</error>');

            return Command::FAILURE;
        }

        $draftFilter = $input->getOption('draft');

        if (is_string($draftFilter)) {
            $complianceTests = array_filter(
                $complianceTests,
                fn ($test): bool => $test->getName() === $draftFilter,
            );

            if ($complianceTests === []) {
                $output->writeln(sprintf('<error>Draft "%s" not found.</error>', $draftFilter));

                return Command::FAILURE;
            }
        }

        $runner = new ComplianceRunner();
        $suites = [];

        foreach ($complianceTests as $compliance) {
            $suites[] = $runner->run($compliance);
        }

        $useCi = $input->getOption('ci') !== false;
        $showFailures = $input->getOption('failures') !== false;

        if ($useCi) {
            $renderer = new CiRenderer($output);
            $renderer->render($suites);

            if ($showFailures) {
                foreach ($suites as $suite) {
                    $renderer->renderFailures($suite);
                }
            }
        } else {
            $summaryRenderer = new SummaryRenderer();
            $summaryRenderer->render($suites);

            if ($showFailures) {
                $detailRenderer = new DetailRenderer();

                foreach ($suites as $suite) {
                    $detailRenderer->render($suite);
                }
            }
        }

        $totalFailed = array_sum(array_map(fn ($s): int => $s->failedTests(), $suites));

        return $totalFailed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
