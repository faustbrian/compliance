<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Commands;

use Cline\Prism\Output\AssertionRenderer;
use Cline\Prism\Output\BenchmarkRenderer;
use Cline\Prism\Output\ComparisonRenderer;
use Cline\Prism\Output\ContinuousIntegrationRenderer;
use Cline\Prism\Output\CoverageRenderer;
use Cline\Prism\Output\DiffRenderer;
use Cline\Prism\Output\FuzzingRenderer;
use Cline\Prism\Output\JsonRenderer;
use Cline\Prism\Output\JunitXmlRenderer;
use Cline\Prism\Output\ProfileRenderer;
use Cline\Prism\Output\SnapshotRenderer;
use Cline\Prism\Output\SummaryRenderer;
use Cline\Prism\Services\BenchmarkService;
use Cline\Prism\Services\ConfigLoader;
use Cline\Prism\Services\CoverageService;
use Cline\Prism\Services\CustomAssertionService;
use Cline\Prism\Services\FilterService;
use Cline\Prism\Services\FuzzingService;
use Cline\Prism\Services\IncrementalService;
use Cline\Prism\Services\InteractiveService;
use Cline\Prism\Services\ParallelRunner;
use Cline\Prism\Services\PrismRunner;
use Cline\Prism\Services\ProgressService;
use Cline\Prism\Services\SnapshotService;
use Cline\Prism\Services\ValidatorComparisonService;
use Cline\Prism\Services\WatchService;
use Cline\Prism\ValueObjects\TestSuite;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_map;
use function array_sum;
use function count;
use function implode;
use function in_array;
use function is_numeric;
use function is_string;
use function sprintf;
use function throw_unless;

/**
 * Console command for executing prism validation tests.
 *
 * Loads prism test configurations, runs validation tests against the
 * configured schemas, and renders results using either CI-friendly plain text
 * output or enhanced terminal output with Termwind. Supports filtering tests
 * by draft/version and displaying detailed failure information.
 *
 * @author Brian Faust <brian@cline.sh>
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
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text (default), json, xml', 'text')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter tests by name pattern (regex)')
            ->addOption('path-filter', null, InputOption::VALUE_REQUIRED, 'Filter test files by path pattern (glob)')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Exclude tests by name pattern (regex)')
            ->addOption('tag', null, InputOption::VALUE_REQUIRED, 'Filter tests by tag')
            ->addOption('parallel', 'j', InputOption::VALUE_REQUIRED, 'Number of parallel workers for test execution', '1')
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Watch test files for changes and re-run tests automatically')
            ->addOption('incremental', null, InputOption::VALUE_NONE, 'Run only tests that have changed since last run')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Run in interactive mode with menu-driven test selection')
            ->addOption('profile', null, InputOption::VALUE_NONE, 'Show performance profiling (slowest tests)')
            ->addOption('baseline', null, InputOption::VALUE_OPTIONAL, 'Save performance baseline with given name', false)
            ->addOption('compare', null, InputOption::VALUE_OPTIONAL, 'Compare against saved baseline', false)
            ->addOption('compare-validators', null, InputOption::VALUE_NONE, 'Compare results across all configured validators')
            ->addOption('update-snapshots', null, InputOption::VALUE_NONE, 'Update test result snapshots')
            ->addOption('check-snapshots', null, InputOption::VALUE_NONE, 'Check current results against snapshots')
            ->addOption('coverage', null, InputOption::VALUE_NONE, 'Show test coverage analysis')
            ->addOption('fuzz', null, InputOption::VALUE_REQUIRED, 'Generate and run N fuzzed tests', '0')
            ->addOption('list-assertions', null, InputOption::VALUE_NONE, 'List available custom assertions');
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
        // If interactive mode is enabled, run interactive interface
        if ($input->getOption('interactive') !== false) {
            return $this->executeInteractive($input, $output);
        }

        // If watch mode is enabled, run in continuous loop
        if ($input->getOption('watch') !== false) {
            return $this->executeWatch($input, $output);
        }

        return $this->runTests($input, $output);
    }

    /**
     * Execute tests in interactive mode with menu-driven selection.
     *
     * Runs the interactive service which provides a menu-driven interface for
     * selecting and executing tests. The interactive mode continues until the
     * user explicitly exits the interface.
     *
     * @param  InputInterface  $input  Console input interface
     * @param  OutputInterface $output Console output interface
     * @return never           Runs indefinitely until user exits
     */
    private function executeInteractive(InputInterface $input, OutputInterface $output): never
    {
        $helper = $this->getHelper('question');

        throw_unless($helper instanceof QuestionHelper, RuntimeException::class, 'Question helper not found');

        $interactiveService = new InteractiveService($helper, $input, $output);

        $interactiveService->run(function () use ($input, $output): void {
            $this->runTests($input, $output);
        });
    }

    /**
     * Execute tests in watch mode with automatic re-running on file changes.
     *
     * Monitors test files for changes and automatically re-runs tests when modifications
     * are detected. This mode continues indefinitely until manually interrupted by the user.
     *
     * @param  InputInterface  $input  Console input interface
     * @param  OutputInterface $output Console output interface
     * @return never           Runs indefinitely until interrupted
     */
    private function executeWatch(InputInterface $input, OutputInterface $output): never
    {
        $configLoader = new ConfigLoader();
        $path = $input->getArgument('path');
        $prismTests = $configLoader->load(is_string($path) ? $path : null);

        if ($prismTests === []) {
            $output->writeln('<error>No prism tests found. Create a prism.php config file.</error>');

            exit(Command::FAILURE);
        }

        $watchService = new WatchService($output);

        // Use the first prism test for watching (could be extended to watch all)
        $prism = $prismTests[0];

        $watchService->watch($prism, function () use ($input, $output): void {
            $this->runTests($input, $output);
        });
    }

    /**
     * Run prism tests and render results.
     *
     * Core test execution logic that loads configuration, applies filters, runs tests
     * in parallel or sequential mode, and renders results in the requested format.
     * Handles special modes like fuzzing, coverage analysis, and validator comparison.
     *
     * @param  InputInterface  $input  Console input interface
     * @param  OutputInterface $output Console output interface
     * @return int             Command::SUCCESS if all tests pass, Command::FAILURE otherwise
     */
    private function runTests(InputInterface $input, OutputInterface $output): int
    {
        // Show available assertions if requested (early exit)
        if ($input->getOption('list-assertions') !== false) {
            // Create an empty assertion service for now - in a real implementation,
            // this would load user-configured assertions from prism.php
            $assertionService = new CustomAssertionService();
            $assertionRenderer = new AssertionRenderer();
            $output->write($assertionRenderer->render($assertionService));

            return Command::SUCCESS;
        }

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

        // Create filter service if any filters are specified
        $nameFilter = $input->getOption('filter');
        $pathFilter = $input->getOption('path-filter');
        $excludeFilter = $input->getOption('exclude');
        $tagFilter = $input->getOption('tag');

        $filterService = null;

        if (is_string($nameFilter) || is_string($pathFilter) || is_string($excludeFilter) || is_string($tagFilter)) {
            $filterService = new FilterService(
                nameFilter: is_string($nameFilter) ? $nameFilter : null,
                pathFilter: is_string($pathFilter) ? $pathFilter : null,
                excludeFilter: is_string($excludeFilter) ? $excludeFilter : null,
                tagFilter: is_string($tagFilter) ? $tagFilter : null,
            );
        }

        // Determine execution mode (parallel or sequential)
        $parallelOption = $input->getOption('parallel');
        $parallelWorkers = is_numeric($parallelOption) ? (int) $parallelOption : 1;
        $useIncremental = $input->getOption('incremental') !== false;
        $runner = new PrismRunner($filterService);
        $incrementalService = $useIncremental ? new IncrementalService() : null;
        $suites = [];

        // Initialize progress tracking if verbose mode is enabled
        $verbose = $output->isVerbose();
        $progressService = null;

        if ($verbose && $parallelWorkers === 1) {
            // Count total tests across all prism instances for progress initialization
            $totalTests = 0;

            foreach ($prismTests as $prism) {
                $testFiles = $runner->collectTestFiles($prism);

                if ($incrementalService instanceof IncrementalService) {
                    $testFiles = $incrementalService->filterChangedFiles($testFiles);
                }

                $totalTests += $runner->countTests($prism, $testFiles);
            }

            $progressService = new ProgressService($output, true);
            $progressService->start($totalTests);
        }

        foreach ($prismTests as $prism) {
            // Filter files for incremental mode
            $testFiles = $runner->collectTestFiles($prism);

            if ($incrementalService instanceof IncrementalService) {
                $testFiles = $incrementalService->filterChangedFiles($testFiles);
            }

            // Run tests
            if ($parallelWorkers > 1) {
                $parallelRunner = new ParallelRunner($runner);
                $suites[] = $parallelRunner->run($prism, $parallelWorkers);
            } else {
                $suites[] = $runner->run($prism, $testFiles, $progressService);
            }

            // Save cache for incremental mode
            if (!$incrementalService instanceof IncrementalService) {
                continue;
            }

            $incrementalService->saveCache($runner->collectTestFiles($prism));
        }

        // Finish progress tracking
        if ($progressService instanceof ProgressService) {
            $progressService->finish();
        }

        $format = $input->getOption('format');
        $showFailures = $input->getOption('failures') !== false;

        // Validate format option
        $validFormats = ['text', 'json', 'xml'];

        if (!in_array($format, $validFormats, true)) {
            $formatStr = is_string($format) ? $format : 'unknown';
            $output->writeln(sprintf('<error>Invalid format "%s". Valid formats: %s</error>', $formatStr, implode(', ', $validFormats)));

            return Command::FAILURE;
        }

        // Render based on format
        match ($format) {
            'json' => new JsonRenderer($output, $showFailures)->render($suites),
            'xml' => new JunitXmlRenderer($output)->render($suites),
            'text' => $this->renderText($input, $output, $suites, $showFailures),
        };

        // Show performance profile if requested
        if ($input->getOption('profile') !== false) {
            $profileRenderer = new ProfileRenderer();
            $output->write($profileRenderer->render($suites));
        }

        // Handle baseline operations
        $benchmarkService = new BenchmarkService();
        $baselineOption = $input->getOption('baseline');
        $compareOption = $input->getOption('compare');

        // Save baseline if requested
        if ($baselineOption !== false) {
            $baselineName = is_string($baselineOption) && $baselineOption !== '' ? $baselineOption : 'default';
            $benchmarkService->saveBaseline($suites, $baselineName);
            $output->writeln(sprintf('<info>Baseline "%s" saved successfully.</info>', $baselineName));
        }

        // Compare against baseline if requested
        if ($compareOption !== false) {
            $baselineName = is_string($compareOption) && $compareOption !== '' ? $compareOption : 'default';
            $baselineData = $benchmarkService->loadBaseline($baselineName);

            if ($baselineData !== null) {
                $benchmarkRenderer = new BenchmarkRenderer();
                $output->write($benchmarkRenderer->render($suites, $baselineData));
            } else {
                $output->writeln(sprintf('<error>Baseline "%s" not found.</error>', $baselineName));
            }
        }

        // Handle snapshot operations
        $snapshotService = new SnapshotService();

        // Update snapshots if requested
        if ($input->getOption('update-snapshots') !== false) {
            $snapshotService->saveSnapshot($suites);
            $output->writeln('<info>Snapshots updated successfully.</info>');
        }

        // Check snapshots if requested
        if ($input->getOption('check-snapshots') !== false) {
            $snapshotRenderer = new SnapshotRenderer();

            foreach ($suites as $suite) {
                $snapshotData = $snapshotService->loadSnapshot($suite->name);

                if ($snapshotData !== null) {
                    /** @var array{results?: array<string, array{passed: bool}>} $snapshotData */
                    $output->write($snapshotRenderer->render($suites, $snapshotData, $suite->name));
                } else {
                    $output->writeln(sprintf('<error>Snapshot for "%s" not found. Run with --update-snapshots to create.</error>', $suite->name));
                }
            }
        }

        // Handle validator comparison if requested
        if ($input->getOption('compare-validators') !== false) {
            if (count($prismTests) < 2) {
                $output->writeln('<error>At least two validators required for comparison. Configure multiple validators in prism.php</error>');

                return Command::FAILURE;
            }

            $validatorMap = [];

            foreach ($prismTests as $prism) {
                $validatorMap[$prism->getName()] = $prism;
            }

            $comparisonService = new ValidatorComparisonService();
            $comparisonData = $comparisonService->compare($validatorMap, $runner);

            $comparisonRenderer = new ComparisonRenderer();
            $output->write($comparisonRenderer->render($comparisonData));
        }

        // Show coverage analysis if requested
        if ($input->getOption('coverage') !== false) {
            $coverageService = new CoverageService();
            $coverageData = $coverageService->analyze($suites);

            $coverageRenderer = new CoverageRenderer();
            $coverageRenderer->render($coverageData);
        }

        // Run fuzzing tests if requested
        $fuzzOption = $input->getOption('fuzz');
        $fuzzIterations = is_numeric($fuzzOption) ? (int) $fuzzOption : 0;

        if ($fuzzIterations > 0) {
            $fuzzingService = new FuzzingService();

            foreach ($prismTests as $prism) {
                $fuzzSuite = $fuzzingService->fuzz($prism, $fuzzIterations);
                $suites[] = $fuzzSuite;

                $fuzzingRenderer = new FuzzingRenderer();
                $output->write($fuzzingRenderer->render($fuzzSuite));
            }
        }

        $totalFailed = array_sum(array_map(fn ($s): int => $s->failedTests(), $suites));

        return $totalFailed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Render test results in text format.
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
            $renderer = new ContinuousIntegrationRenderer($output);
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
                $diffRenderer = new DiffRenderer($output);
                $failureNumber = 1;

                foreach ($suites as $suite) {
                    foreach ($suite->failures() as $failure) {
                        $diffRenderer->renderFailure($failureNumber++, $failure);
                    }
                }
            }
        }
    }
}
