<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use Cline\Prism\Contracts\PrismTestInterface;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;
use Symfony\Component\Process\Process;
use Throwable;

use function addslashes;
use function array_chunk;
use function count;
use function file_get_contents;
use function getcwd;
use function is_array;
use function max;
use function microtime;
use function serialize;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function unserialize;

/**
 * Orchestrates parallel execution of prism test suites across multiple processes.
 *
 * Divides test files into batches and runs each batch in a separate PHP process,
 * enabling faster test execution on multi-core systems. Results from all processes
 * are collected and merged into a single test suite report.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ParallelRunner
{
    /**
     * Create a new parallel runner instance.
     *
     * @param PrismRunner $runner The sequential test runner used to execute test batches
     *                            within each parallel process and to collect test files
     */
    public function __construct(
        private PrismRunner $runner,
    ) {}

    /**
     * Execute a prism test suite in parallel across multiple processes.
     *
     * Divides test files into equal batches and spawns separate PHP processes to
     * execute each batch concurrently. Falls back to sequential execution if only
     * one worker is requested or if there's only one test file. Results from all
     * worker processes are collected and merged into a single test suite.
     *
     * @param  PrismTestInterface $prism   The prism test instance defining test directory
     *                                     and validation logic to be serialized and passed
     *                                     to worker processes
     * @param  int                $workers Number of parallel worker processes to spawn.
     *                                     A value of 1 or fewer triggers sequential execution
     * @return TestSuite          Complete test suite containing aggregated results from all
     *                            worker processes with total execution time
     */
    public function run(PrismTestInterface $prism, int $workers): TestSuite
    {
        $startTime = microtime(true);

        // Collect all test files
        $testFiles = $this->runner->collectTestFiles($prism);

        if ($testFiles === []) {
            return new TestSuite(
                name: $prism->getName(),
                results: [],
                duration: microtime(true) - $startTime,
            );
        }

        // If only one worker or fewer files than workers, run sequentially
        if ($workers <= 1 || count($testFiles) <= 1) {
            return $this->runner->run($prism);
        }

        // Divide files into batches for parallel execution
        $batches = $this->createBatches($testFiles, $workers);
        $processes = [];
        $outputFiles = [];

        // Start a process for each batch
        foreach ($batches as $batch) {
            $outputFile = tempnam(sys_get_temp_dir(), 'prism_');
            $outputFiles[] = $outputFile;

            $process = $this->createBatchProcess($prism, $batch, $outputFile);
            $process->start();
            $processes[] = $process;
        }

        // Wait for all processes to complete and collect results
        $allResults = [];

        foreach ($processes as $index => $process) {
            $process->wait();

            $outputFile = $outputFiles[$index];
            $results = $this->loadResults($outputFile);
            $allResults = [...$allResults, ...$results];

            unlink($outputFile);
        }

        $duration = microtime(true) - $startTime;

        return new TestSuite(
            name: $prism->getName(),
            results: $allResults,
            duration: $duration,
        );
    }

    /**
     * Divide test files into batches for parallel processing.
     *
     * Calculates optimal batch size based on total files and worker count, then
     * chunks the file array into equal-sized batches. Ensures at least one file
     * per batch to prevent empty batches.
     *
     * @param  array<int, string>             $testFiles Array of absolute paths to test files
     *                                                   to be divided among workers
     * @param  int                            $workers   Number of worker processes, used to
     *                                                   calculate batch size
     * @return array<int, array<int, string>> Array of batches, where each batch is an array
     *                                        of test file paths to be executed by one worker
     */
    private function createBatches(array $testFiles, int $workers): array
    {
        $batchSize = max(1, (int) (count($testFiles) / $workers));

        return array_chunk($testFiles, $batchSize);
    }

    /**
     * Create a PHP process to execute a batch of test files.
     *
     * Generates inline PHP code that deserializes the prism instance and test files,
     * runs each test file through the PrismRunner, and serializes the results to a
     * temporary file. The code is executed in a separate PHP process via Symfony Process.
     *
     * @param  PrismTestInterface $prism      The prism test instance to be serialized
     *                                        and passed to the worker process
     * @param  array<int, string> $batch      Array of test file paths to execute
     *                                        in this worker process
     * @param  string             $outputFile Absolute path to temporary file where serialized
     *                                        test results will be written by the worker
     * @return Process            Symfony Process instance configured to execute the batch,
     *                            ready to be started
     */
    private function createBatchProcess(PrismTestInterface $prism, array $batch, string $outputFile): Process
    {
        $cwd = getcwd() ?: '.';
        $prismSerialized = serialize($prism);
        $batchSerialized = serialize($batch);

        // Create inline PHP code to run the batch
        $code = <<<'PHP'
        require '%s/vendor/autoload.php';

        $prism = unserialize('%s');
        $batch = unserialize('%s');
        $runner = new \Cline\Prism\Services\PrismRunner();

        $results = [];
        foreach ($batch as $file) {
            $fileResults = $runner->runTestFile($prism, $file);
            $results = array_merge($results, $fileResults);
        }

        file_put_contents('%s', serialize($results));
        PHP;

        $script = sprintf(
            $code,
            $cwd,
            addslashes($prismSerialized),
            addslashes($batchSerialized),
            $outputFile,
        );

        return new Process(['php', '-r', $script], $cwd);
    }

    /**
     * Load test results from a temporary output file.
     *
     * Reads and deserializes test results written by a worker process. Handles
     * failures gracefully by returning an empty array if the file cannot be read
     * or the contents cannot be unserialized.
     *
     * @param  string                 $outputFile Absolute path to temporary file containing
     *                                            serialized test results from a worker process
     * @return array<int, TestResult> Array of test results from the worker, or empty array
     *                                if file cannot be read or deserialization fails
     */
    private function loadResults(string $outputFile): array
    {
        try {
            $contents = file_get_contents($outputFile);
        } catch (Throwable) {
            return [];
        }

        if ($contents === false) {
            return [];
        }

        try {
            $unserialized = unserialize($contents);

            if (!is_array($unserialized)) {
                return [];
            }

            /** @var array<int, TestResult> */
            return $unserialized;
        } catch (Throwable) {
            return [];
        }
    }
}
