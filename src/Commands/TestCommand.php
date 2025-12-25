<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Commands;

use Cline\Prism\Output\CiRenderer;
use Cline\Prism\Output\DetailRenderer;
use Cline\Prism\Output\JsonRenderer;
use Cline\Prism\Output\SummaryRenderer;
use Cline\Prism\Output\XmlRenderer;
use Cline\Prism\Output\YamlRenderer;
use Cline\Prism\Services\PrismRunner;
use Cline\Prism\Services\ConfigLoader;
use Cline\Prism\ValueObjects\TestSuite;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_map;
use function array_sum;
use function implode;
use function in_array;
use function is_string;
use function sprintf;

/**
 * Console command for executing prism validation tests.
 *
 * Loads prism test configurations, runs validation tests against the
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
            ->setDescription('Run prism tests')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to prism.php configuration file')
            ->addOption('ci', null, InputOption::VALUE_NONE, 'Use CI-friendly output (no Termwind)')
            ->addOption('failures', null, InputOption::VALUE_NONE, 'Show detailed failure information')
            ->addOption('draft', null, InputOption::VALUE_REQUIRED, 'Run tests for specific draft only')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text (default), json, yaml, xml', 'text');
    }

    /**
     * Execute the prism test command.
     *
     * Loads test configurations, optionally filters by draft name, executes all
     * matching prism tests, and renders results using the appropriate output
     * renderer based on the --ci flag. Returns success only if all tests pass.
     *
     * @param  InputInterface  $input  Console input interface providing command options and arguments
     * @param  OutputInterface $output Console output interface for writing test results and messages
     * @return int             Command::SUCCESS (0) if all tests pass, Command::FAILURE (1) if any test fails or configuration errors occur
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configLoader = new ConfigLoader();
        $path = $input->getArgument('path');
        $prismTests = $configLoader->load(is_string($path) ? $path : null);

        if ($prismTests === []) {
            $output->writeln('<error>No prism tests found. Create a prism.php config file.</error>');

            return Command::FAILURE;
        }

        $draftFilter = $input->getOption('draft');

        if (is_string($draftFilter)) {
            $prismTests = array_filter(
                $prismTests,
                fn ($test): bool => $test->getName() === $draftFilter,
            );

            if ($prismTests === []) {
                $output->writeln(sprintf('<error>Draft "%s" not found.</error>', $draftFilter));

                return Command::FAILURE;
            }
        }

        $runner = new PrismRunner();
        $suites = [];

        foreach ($prismTests as $prism) {
            $suites[] = $runner->run($prism);
        }

        $format = $input->getOption('format');
        $showFailures = $input->getOption('failures') !== false;

        // Validate format option
        $validFormats = ['text', 'json', 'yaml', 'xml'];

        if (!in_array($format, $validFormats, true)) {
            $formatStr = is_string($format) ? $format : 'unknown';
            $output->writeln(sprintf('<error>Invalid format "%s". Valid formats: %s</error>', $formatStr, implode(', ', $validFormats)));

            return Command::FAILURE;
        }

        // Render based on format
        match ($format) {
            'json' => new JsonRenderer($output, $showFailures)->render($suites),
            'yaml' => new YamlRenderer($output, $showFailures)->render($suites),
            'xml' => new XmlRenderer($output, $showFailures)->render($suites),
            'text' => $this->renderText($input, $output, $suites, $showFailures),
        };

        $totalFailed = array_sum(array_map(fn ($s): int => $s->failedTests(), $suites));

        return $totalFailed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Render test results in text format (existing behavior).
     *
     * Uses either CI-friendly plain text output or enhanced Termwind output
     * based on the --ci flag, with optional detailed failure information.
     *
     * @param InputInterface        $input        Console input interface providing command options
     * @param OutputInterface       $output       Console output interface for writing test results
     * @param array<int, TestSuite> $suites       Collection of test suite results to render
     * @param bool                  $showFailures Whether to show detailed failure information
     */
    private function renderText(InputInterface $input, OutputInterface $output, array $suites, bool $showFailures): void
    {
        $useCi = $input->getOption('ci') !== false;

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
    }
}
