<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use Cline\Prism\Contracts\PrismTestInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;

use function array_any;
use function filemtime;
use function is_dir;
use function sprintf;

/**
 * Service for monitoring test files and triggering re-runs on changes.
 *
 * Watches the test directory for file modifications and automatically
 * re-executes tests when changes are detected. Uses simple polling
 * mechanism for cross-platform compatibility.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class WatchService
{
    /**
     * Create a new watch service instance.
     *
     * @param OutputInterface $output       Symfony console output interface for displaying
     *                                      status messages and change notifications to the user
     * @param int             $pollInterval Number of seconds to wait between file system scans.
     *                                      Defaults to 1 second for responsive change detection
     *                                      while minimizing system load
     */
    public function __construct(
        private OutputInterface $output,
        private int $pollInterval = 1,
    ) {}

    /**
     * Watch test directory and execute callback when changes are detected.
     *
     * Performs an initial scan and test run, then enters an infinite loop that
     * periodically checks for file modifications. When changes are detected,
     * displays a notification and executes the callback. This continues until
     * the process is interrupted by the user (Ctrl+C).
     *
     * @param  PrismTestInterface $prism    The prism test instance providing the test
     *                                      directory path to monitor
     * @param  callable           $callback Callback function to execute when file changes
     *                                      are detected. Typically runs the test suite
     * @return never              Runs indefinitely in a polling loop until interrupted
     *                            by user or system signal
     */
    public function watch(PrismTestInterface $prism, callable $callback): never
    {
        $this->output->writeln('<info>Watching for file changes... (Press Ctrl+C to stop)</info>');

        // Initial scan
        $fileTimes = $this->scanFiles($prism);
        $callback();

        // @phpstan-ignore-next-line while.alwaysTrue (Intentional infinite loop for watch mode)
        while (true) {
            Sleep::sleep($this->pollInterval);

            $currentFileTimes = $this->scanFiles($prism);

            if (!$this->hasChanges($fileTimes, $currentFileTimes)) {
                continue;
            }

            $this->output->writeln(sprintf(
                "\n<fg=yellow>Change detected at %s, re-running tests...</>\n",
                Date::now()->format('H:i:s'),
            ));

            $callback();
            $fileTimes = $currentFileTimes;
        }
    }

    /**
     * Scan test directory and capture file modification times.
     *
     * Recursively traverses the test directory to find all JSON files and records
     * their modification timestamps. This snapshot is used to detect changes on
     * subsequent scans.
     *
     * @param  PrismTestInterface $prism The prism test instance providing the test
     *                                   directory path to scan
     * @return array<string, int> Associative array mapping absolute file paths to
     *                            Unix timestamps of last modification, or empty array
     *                            if directory doesn't exist
     */
    private function scanFiles(PrismTestInterface $prism): array
    {
        $testDir = $prism->getTestDirectory();
        $fileTimes = [];

        if (!is_dir($testDir)) {
            return $fileTimes;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($testDir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            if ($file->getExtension() !== 'json') {
                continue;
            }

            $filePath = $file->getPathname();
            $modTime = filemtime($filePath);

            if ($modTime === false) {
                continue;
            }

            $fileTimes[$filePath] = $modTime;
        }

        return $fileTimes;
    }

    /**
     * Check if any files have been added, modified, or deleted.
     *
     * Compares two snapshots of file modification times to detect changes. Identifies
     * new files, modified files (different timestamps), and deleted files (present in
     * old but not in new snapshot).
     *
     * @param  array<string, int> $old Previous file modification time snapshot from earlier scan
     * @param  array<string, int> $new Current file modification time snapshot from latest scan
     * @return bool               True if any files were added, modified, or deleted,
     *                            false if no changes detected
     */
    private function hasChanges(array $old, array $new): bool
    {
        // Check for new or modified files
        foreach ($new as $path => $mtime) {
            if (!isset($old[$path]) || $old[$path] !== $mtime) {
                return true;
            }
        }

        return array_any($old, fn ($mtime, $path): bool => !isset($new[$path]));
    }
}
