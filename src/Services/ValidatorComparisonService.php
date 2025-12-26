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

use function array_first;
use function array_keys;
use function count;
use function max;
use function mb_strpos;
use function mb_substr;
use function sprintf;

/**
 * Service for comparing validation results across multiple validator implementations.
 *
 * Executes the same test suite against multiple validators to detect inconsistencies
 * in validation behavior. Useful for ensuring validator implementations conform to
 * the same specification or identifying edge cases where validators disagree.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ValidatorComparisonService
{
    /**
     * Compare test results across multiple validators to detect inconsistencies.
     *
     * Executes the same test suite against each validator and analyzes the results
     * to identify tests where validators disagree. Returns a comprehensive report
     * including the list of validators tested, total test count, and detailed
     * discrepancy information for each test where validators produced different outcomes.
     *
     * @param  array<string, PrismTestInterface> $validators Map of validator names to prism test
     *                                                       instances. Requires at least two validators
     *                                                       for meaningful comparison
     * @param  PrismRunner                       $runner     Test runner instance used to execute tests
     *                                                       against each validator
     * @return array<string, mixed>              Associative array containing 'validators' (list of names),
     *                                           'total_tests' (count), 'discrepancies_count' (count),
     *                                           and 'discrepancies' (array of mismatches), or 'error'
     *                                           message if fewer than two validators provided
     */
    public function compare(array $validators, PrismRunner $runner): array
    {
        if (count($validators) < 2) {
            return [
                'error' => 'At least two validators required for comparison',
                'discrepancies' => [],
            ];
        }

        // Run tests for each validator
        $results = [];

        foreach ($validators as $name => $validator) {
            $suite = $runner->run($validator);
            $results[$name] = $this->indexResultsById($suite->results);
        }

        // Find discrepancies
        $discrepancies = $this->findDiscrepancies($results);

        return [
            'validators' => array_keys($validators),
            'total_tests' => count(array_first($results)),
            'discrepancies_count' => count($discrepancies),
            'discrepancies' => $discrepancies,
        ];
    }

    /**
     * Index test results by their ID for easy comparison.
     *
     * Converts a sequential array of test results into an associative array
     * keyed by test ID, enabling efficient lookup when comparing results from
     * different validators for the same test.
     *
     * @param  array<int, TestResult>    $results Sequential array of test results
     *                                            from a single validator run
     * @return array<string, TestResult> Associative array with test IDs as keys
     *                                   and TestResult objects as values
     */
    private function indexResultsById(array $results): array
    {
        $indexed = [];

        foreach ($results as $result) {
            // Normalize ID by removing validator name prefix (first component before first colon)
            $normalizedId = mb_substr($result->id, mb_strpos($result->id, ':') + 1);
            $indexed[$normalizedId] = $result;
        }

        return $indexed;
    }

    /**
     * Find discrepancies between validator results.
     *
     * Iterates through all test IDs and compares the actual validation outcomes
     * across all validators. Builds a list of tests where validators disagreed,
     * including detailed outcome information and agreement percentage for each
     * discrepancy.
     *
     * @param  array<string, array<string, TestResult>> $results Map of validator names to their
     *                                                           indexed test results
     * @return array<int, array<string, mixed>>         Array of discrepancies, each containing
     *                                                  test_id, description, outcomes per validator,
     *                                                  and agreement percentage
     */
    private function findDiscrepancies(array $results): array
    {
        $discrepancies = [];
        $validatorNames = array_keys($results);
        $firstValidator = $validatorNames[0];
        $testIds = array_keys($results[$firstValidator]);

        foreach ($testIds as $testId) {
            $outcomes = [];

            // Collect outcomes from all validators for this test
            foreach ($validatorNames as $validatorName) {
                if (!isset($results[$validatorName][$testId])) {
                    continue;
                }

                $result = $results[$validatorName][$testId];
                $outcomes[$validatorName] = [
                    'passed' => $result->passed,
                    'actual' => $result->actual,
                    'expected' => $result->expected,
                ];
            }

            // Check if all validators agree
            if ($this->allValidatorsAgree($outcomes)) {
                continue;
            }

            $discrepancies[] = [
                'test_id' => $testId,
                'description' => $results[$firstValidator][$testId]->description,
                'outcomes' => $outcomes,
                'agreement' => $this->calculateAgreement($outcomes),
            ];
        }

        return $discrepancies;
    }

    /**
     * Check if all validators produced the same validation result.
     *
     * Compares the actual value across all validators to determine if they
     * reached consensus. An empty outcomes array is considered as agreement.
     *
     * @param  array<string, array<string, bool>> $outcomes Map of validator names to their
     *                                                      outcome data including actual status
     * @return bool                               True if all validators produced the same
     *                                            actual result, false if any disagreement exists
     */
    private function allValidatorsAgree(array $outcomes): bool
    {
        if ($outcomes === []) {
            return true;
        }

        $first = null;

        foreach ($outcomes as $outcome) {
            if ($first === null) {
                $first = $outcome['actual'];

                continue;
            }

            if ($outcome['actual'] !== $first) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate the percentage of validators in the majority opinion.
     *
     * Counts how many validators voted true vs false for actual, then
     * calculates what percentage the larger group represents. This indicates
     * the level of consensus even when validators disagree.
     *
     * @param  array<string, array<string, bool>> $outcomes Map of validator names to their
     *                                                      outcome data including actual status
     * @return string                             Percentage of validators in majority group,
     *                                            formatted as "XX.X%", or "0%" if no outcomes
     */
    private function calculateAgreement(array $outcomes): string
    {
        if ($outcomes === []) {
            return '0%';
        }

        // Count outcomes
        $validCounts = ['true' => 0, 'false' => 0];

        foreach ($outcomes as $outcome) {
            $key = $outcome['actual'] ? 'true' : 'false';
            ++$validCounts[$key];
        }

        $majority = max($validCounts);
        $total = count($outcomes);
        $percentage = ($majority / $total) * 100;

        return sprintf('%.1f%%', $percentage);
    }
}
