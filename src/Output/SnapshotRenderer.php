<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Output;

use Cline\Prism\ValueObjects\TestSuite;

use function array_find;
use function array_keys;
use function count;
use function in_array;
use function sprintf;

/**
 * Renders snapshot comparison results showing changes in test outcomes.
 *
 * Compares current test results against stored snapshots to detect
 * unexpected changes in validation behavior, helping catch regressions.
 * Identifies changed tests, new tests, and missing tests with detailed
 * pass/fail status comparisons.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class SnapshotRenderer
{
    /**
     * Render snapshot comparison report.
     *
     * Compares a specific test suite's current results against its stored
     * snapshot to identify changes. Reports tests that changed pass/fail
     * status, new tests not in the snapshot, and tests removed since the
     * snapshot was created. Provides guidance for updating snapshots when
     * mismatches are detected.
     *
     * @param array<int, TestSuite>                               $suites       Current test suites containing
     *                                                                          up-to-date test results
     * @param array{results?: array<string, array{passed: bool}>} $snapshotData Stored snapshot data containing
     *                                                                          results array with test IDs and
     *                                                                          their historical pass/fail status
     * @param string                                              $suiteName    Name of the specific test suite
     *                                                                          to compare against the snapshot
     *
     * @return string Formatted snapshot comparison report with changes, additions, and deletions
     */
    public function render(array $suites, array $snapshotData, string $suiteName): string
    {
        $output = sprintf("\n<fg=cyan;options=bold>Snapshot Comparison - %s</>\n\n", $suiteName);
        $suite = array_find($suites, fn ($s): bool => $s->name === $suiteName);

        if ($suite === null) {
            return $output."<fg=red>Suite not found in current results.</>\n";
        }

        /** @var array<string, array{passed: bool}> $snapshotResults */
        $snapshotResults = $snapshotData['results'] ?? [];
        $changedTests = [];
        $newTests = [];
        $missingTests = [];

        // Find changed and new tests
        foreach ($suite->results as $result) {
            if (!isset($snapshotResults[$result->id])) {
                $newTests[] = $result->id;

                continue;
            }

            $snapshotResult = $snapshotResults[$result->id];

            if ($result->passed === $snapshotResult['passed']) {
                continue;
            }

            $changedTests[] = [
                'id' => $result->id,
                'old_status' => $snapshotResult['passed'] ? 'PASS' : 'FAIL',
                'new_status' => $result->passed ? 'PASS' : 'FAIL',
            ];
        }

        // Find missing tests
        $currentTestIds = [];

        foreach ($suite->results as $result) {
            $currentTestIds[] = $result->id;
        }

        foreach (array_keys($snapshotResults) as $snapshotTestId) {
            if (in_array($snapshotTestId, $currentTestIds, true)) {
                continue;
            }

            $missingTests[] = $snapshotTestId;
        }

        // Report changes
        if ($changedTests === [] && $newTests === [] && $missingTests === []) {
            $output .= "<fg=green>✓ No changes detected - all tests match snapshot</>\n";
        } else {
            if ($changedTests !== []) {
                $output .= sprintf("<fg=yellow>Changed Tests (%d):</>\n", count($changedTests));

                foreach ($changedTests as $change) {
                    $output .= sprintf(
                        "  <fg=yellow>%s</> %s → %s\n",
                        $change['id'],
                        $change['old_status'],
                        $change['new_status'],
                    );
                }

                $output .= "\n";
            }

            if ($newTests !== []) {
                $output .= sprintf("<fg=cyan>New Tests (%d):</>\n", count($newTests));

                foreach ($newTests as $testId) {
                    $output .= sprintf("  <fg=cyan>%s</>\n", $testId);
                }

                $output .= "\n";
            }

            if ($missingTests !== []) {
                $output .= sprintf("<fg=red>Missing Tests (%d):</>\n", count($missingTests));

                foreach ($missingTests as $testId) {
                    $output .= sprintf("  <fg=red>%s</>\n", $testId);
                }

                $output .= "\n";
            }

            $output .= "<fg=red;options=bold>⚠ Snapshot mismatch detected! Use --update-snapshots to update.</>\n";
        }

        return $output."\n";
    }
}
