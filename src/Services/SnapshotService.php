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
 * Service for managing test result snapshots.
 *
 * Stores expected test outcomes as snapshots for regression testing,
 * enabling detection of unexpected changes in validation behavior.
 * Snapshots are stored as JSON files in .prism/snapshots/ directory.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class SnapshotService
{
    private string $snapshotDir;

    /**
     * Create a new snapshot service instance.
     *
     * @param null|string $snapshotDir Optional custom directory path for storing snapshots.
     *                                 If not provided, defaults to .prism/snapshots in the
     *                                 current working directory
     */
    public function __construct(?string $snapshotDir = null)
    {
        $this->snapshotDir = $snapshotDir ?? (getcwd() ?: '.').'/.prism/snapshots';
    }

    /**
     * Save test suites as snapshots for future comparison.
     *
     * Extracts key metrics and individual test results from each suite and persists
     * them as JSON files in the snapshot directory. Creates the directory if it doesn't
     * exist. Each suite is saved as a separate file named after the suite.
     *
     * @param array<int, TestSuite> $suites Array of test suites to save as snapshots,
     *                                      typically containing baseline results for regression testing
     */
    public function saveSnapshot(array $suites): void
    {
        foreach ($suites as $suite) {
            $snapshotData = [
                'total_tests' => $suite->totalTests(),
                'passed_tests' => $suite->passedTests(),
                'failed_tests' => $suite->failedTests(),
                'pass_rate' => $suite->passRate(),
                'results' => [],
            ];

            foreach ($suite->results as $result) {
                $snapshotData['results'][$result->id] = [
                    'passed' => $result->passed,
                    'expected' => $result->expected,
                    'actual' => $result->actual,
                ];
            }

            $this->ensureSnapshotDir();

            $snapshotPath = $this->getSnapshotPath($suite->name);
            file_put_contents($snapshotPath, json_encode($snapshotData, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Load snapshot data for comparison with current results.
     *
     * Retrieves previously saved snapshot data for a test suite by name. Returns
     * the snapshot data as an associative array containing test metrics and results,
     * or null if the snapshot file doesn't exist or cannot be parsed.
     *
     * @param  string                    $suiteName Name of the test suite to load snapshot for
     * @return null|array<string, mixed> Snapshot data containing total_tests, passed_tests,
     *                                   failed_tests, pass_rate, and individual test results,
     *                                   or null if snapshot not found or invalid
     */
    public function loadSnapshot(string $suiteName): ?array
    {
        $snapshotPath = $this->getSnapshotPath($suiteName);

        if (!file_exists($snapshotPath)) {
            return null;
        }

        $contents = file_get_contents($snapshotPath);

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
     * Get the absolute file path for a suite's snapshot.
     *
     * @param  string $suiteName Name of the test suite
     * @return string Absolute path to the snapshot JSON file
     */
    private function getSnapshotPath(string $suiteName): string
    {
        return $this->snapshotDir.'/'.$suiteName.'.json';
    }

    /**
     * Ensure the snapshot directory exists, creating it if necessary.
     *
     * Creates the directory with 0755 permissions and all parent directories
     * as needed. Does nothing if the directory already exists.
     */
    private function ensureSnapshotDir(): void
    {
        if (is_dir($this->snapshotDir)) {
            return;
        }

        mkdir($this->snapshotDir, 0o755, true);
    }
}
