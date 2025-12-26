<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use Cline\Prism\ValueObjects\TestSuite;

use const JSON_PRETTY_PRINT;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;

/**
 * Service for managing performance benchmarks and baselines.
 *
 * Stores test suite performance metrics as baselines for future comparison,
 * enabling detection of performance regressions and improvements over time.
 * Baselines are stored as JSON files in .prism/baselines/ directory.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class BenchmarkService
{
    private string $baselineDir;

    /**
     * Create a new benchmark service instance.
     *
     * @param null|string $baselineDir Directory path for storing baseline files. Defaults to
     *                                 .prism/baselines/ in the current working directory if not
     *                                 specified. Used to organize performance baseline data for
     *                                 regression tracking and comparison across test runs.
     */
    public function __construct(
        ?string $baselineDir = null,
    ) {
        $this->baselineDir = $baselineDir ?? (getcwd() ?: '.').'/.prism/baselines';
    }

    /**
     * Save test suites as baseline for future comparison.
     *
     * Extracts performance metrics from test suites and stores them as a JSON
     * baseline file. Captures total duration per suite, total test count, and
     * individual test timings for granular performance regression detection.
     *
     * @param array<int, TestSuite> $suites Test suites to save as baseline, containing test
     *                                      results with duration metrics for each test case
     * @param string                $name   Baseline name for the stored file (default: 'default').
     *                                      Allows multiple baseline sets for different scenarios
     *                                      like 'production', 'development', or 'ci'
     */
    public function saveBaseline(array $suites, string $name = 'default'): void
    {
        $baselineData = [];

        foreach ($suites as $suite) {
            $testTimings = [];

            foreach ($suite->results as $result) {
                $testTimings[$result->id] = $result->duration;
            }

            $baselineData[$suite->name] = [
                'total_duration' => $suite->duration,
                'total_tests' => $suite->totalTests(),
                'test_timings' => $testTimings,
            ];
        }

        $this->ensureBaselineDir();

        $baselinePath = $this->getBaselinePath($name);
        file_put_contents($baselinePath, json_encode($baselineData, JSON_PRETTY_PRINT));
    }

    /**
     * Load baseline data for comparison.
     *
     * Retrieves previously saved baseline performance data from JSON storage.
     * Returns null if the baseline file doesn't exist or cannot be read,
     * allowing graceful handling of first-time runs or missing baselines.
     *
     * @param  string                    $name Baseline name to load (default: 'default').
     *                                         Must match the name used when saving the baseline
     * @return null|array<string, mixed> Baseline data containing suite names, durations, and
     *                                   individual test timings, or null if baseline not found
     */
    public function loadBaseline(string $name = 'default'): ?array
    {
        $baselinePath = $this->getBaselinePath($name);

        if (!file_exists($baselinePath)) {
            return null;
        }

        $contents = file_get_contents($baselinePath);

        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> */
        return $decoded;
    }

    /**
     * Get path to baseline file.
     *
     * @param  string $name Baseline name
     * @return string Absolute file path to the baseline JSON file
     */
    private function getBaselinePath(string $name): string
    {
        return $this->baselineDir.'/'.$name.'.json';
    }

    /**
     * Ensure baseline directory exists.
     *
     * Creates the baseline directory with appropriate permissions (0755) if it
     * doesn't already exist. Required before writing baseline files to prevent
     * file I/O errors.
     */
    private function ensureBaselineDir(): void
    {
        if (is_dir($this->baselineDir)) {
            return;
        }

        mkdir($this->baselineDir, 0o755, true);
    }
}
