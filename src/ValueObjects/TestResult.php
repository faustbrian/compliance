<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\ValueObjects;

/**
 * Immutable value object representing the outcome of a single prism test case.
 *
 * Captures all metadata about a test execution including its identity, location,
 * expected vs actual validation results, and any error messages. Provides named
 * constructors for creating passing and failing test results with appropriate defaults.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class TestResult
{
    /**
     * Create a new test result instance.
     *
     * @param string             $id          Unique identifier for the test in format "suite:file:groupIndex:testIndex"
     * @param string             $file        Relative path to the test file within the test directory
     * @param string             $group       Human-readable description of the test group this case belongs to
     * @param string             $description Human-readable description of the specific test case
     * @param mixed              $data        The input data that was validated during the test
     * @param bool               $expected    Whether the test expected validation to pass (true) or fail (false)
     * @param bool               $actual      Whether the validation actually passed (true) or failed (false)
     * @param bool               $passed      Whether the test passed (actual result matched expected result)
     * @param null|string        $error       Optional error message captured during validation, typically from exceptions
     * @param float              $duration    Execution time in seconds for this specific test
     * @param array<int, string> $tags        Tags for organizing and filtering tests
     */
    public function __construct(
        public string $id,
        public string $file,
        public string $group,
        public string $description,
        public mixed $data,
        public bool $expected,
        public bool $actual,
        public bool $passed,
        public ?string $error = null,
        public float $duration = 0.0,
        public array $tags = [],
    ) {}

    /**
     * Create a passing test result.
     *
     * Convenience constructor for test cases where the actual validation result
     * matched the expected result. Automatically sets actual to match
     * expected and marks the test as passed.
     *
     * @param  string             $id          Unique identifier for the test in format "suite:file:groupIndex:testIndex"
     * @param  string             $file        Relative path to the test file within the test directory
     * @param  string             $group       Human-readable description of the test group this case belongs to
     * @param  string             $description Human-readable description of the specific test case
     * @param  mixed              $data        The input data that was validated during the test
     * @param  bool               $expected    Whether the test expected validation to pass (true) or fail (false)
     * @param  float              $duration    Execution time in seconds for this specific test
     * @param  array<int, string> $tags        Tags for organizing and filtering tests
     * @return self               New passing test result instance
     */
    public static function pass(
        string $id,
        string $file,
        string $group,
        string $description,
        mixed $data,
        bool $expected,
        float $duration = 0.0,
        array $tags = [],
    ): self {
        return new self(
            id: $id,
            file: $file,
            group: $group,
            description: $description,
            data: $data,
            expected: $expected,
            actual: $expected,
            passed: true,
            duration: $duration,
            tags: $tags,
        );
    }

    /**
     * Create a failing test result.
     *
     * Convenience constructor for test cases where the actual validation result
     * did not match the expected result. Marks the test as failed and optionally
     * captures an error message explaining the failure.
     *
     * @param  string             $id          Unique identifier for the test in format "suite:file:groupIndex:testIndex"
     * @param  string             $file        Relative path to the test file within the test directory
     * @param  string             $group       Human-readable description of the test group this case belongs to
     * @param  string             $description Human-readable description of the specific test case
     * @param  mixed              $data        The input data that was validated during the test
     * @param  bool               $expected    Whether the test expected validation to pass (true) or fail (false)
     * @param  bool               $actual      Whether the validation actually passed (true) or failed (false)
     * @param  null|string        $error       Optional error message captured during validation, typically from exceptions
     * @param  float              $duration    Execution time in seconds for this specific test
     * @param  array<int, string> $tags        Tags for organizing and filtering tests
     * @return self               New failing test result instance
     */
    public static function fail(
        string $id,
        string $file,
        string $group,
        string $description,
        mixed $data,
        bool $expected,
        bool $actual,
        ?string $error = null,
        float $duration = 0.0,
        array $tags = [],
    ): self {
        return new self(
            id: $id,
            file: $file,
            group: $group,
            description: $description,
            data: $data,
            expected: $expected,
            actual: $actual,
            passed: false,
            error: $error,
            duration: $duration,
            tags: $tags,
        );
    }
}
