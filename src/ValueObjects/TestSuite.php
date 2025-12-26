<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\ValueObjects;

use function array_filter;
use function array_values;
use function count;

/**
 * Immutable value object representing a complete prism test suite execution.
 *
 * Aggregates all test results for a prism test run with execution timing
 * and provides convenient methods for calculating pass rates and extracting
 * failures. Serves as the primary output from the PrismRunner service.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class TestSuite
{
    /**
     * Create a new test suite instance.
     *
     * @param string                 $name     Human-readable name of the prism test suite
     * @param array<int, TestResult> $results  Complete collection of test results from all test cases
     *                                         executed during this suite run
     * @param float                  $duration Total execution time in seconds for the entire test suite
     */
    public function __construct(
        public string $name,
        public array $results,
        public float $duration,
    ) {}

    /**
     * Get the total number of tests executed in this suite.
     *
     * @return int Count of all test cases, both passing and failing
     */
    public function totalTests(): int
    {
        return count($this->results);
    }

    /**
     * Get the number of tests that passed in this suite.
     *
     * @return int Count of test cases where actual validation result matched expected result
     */
    public function passedTests(): int
    {
        return count(array_filter($this->results, fn (TestResult $r): bool => $r->passed));
    }

    /**
     * Get the number of tests that failed in this suite.
     *
     * @return int Count of test cases where actual validation result did not match expected result
     */
    public function failedTests(): int
    {
        return count(array_filter($this->results, fn (TestResult $r): bool => !$r->passed));
    }

    /**
     * Calculate the percentage of tests that passed.
     *
     * @return float Pass rate as a percentage from 0.0 to 100.0, or 0.0 if no tests were executed
     */
    public function passRate(): float
    {
        if ($this->totalTests() === 0) {
            return 0.0;
        }

        return ($this->passedTests() / $this->totalTests()) * 100;
    }

    /**
     * Extract all failing test results from this suite.
     *
     * Filters the results to include only tests where the actual validation
     * result did not match the expected result. Re-indexes the array to ensure
     * sequential numeric keys.
     *
     * @return array<int, TestResult> Array of failed test results with sequential numeric keys,
     *                                or empty array if all tests passed
     */
    public function failures(): array
    {
        return array_values(array_filter($this->results, fn (TestResult $r): bool => !$r->passed));
    }
}
