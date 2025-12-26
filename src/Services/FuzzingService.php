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
use Throwable;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

use function count;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function mb_strlen;
use function microtime;
use function random_int;
use function sprintf;
use function str_repeat;

/**
 * Service for generating and executing fuzzed test cases.
 *
 * Automatically generates random test data to discover edge cases and
 * unexpected validation behavior through property-based testing.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class FuzzingService
{
    /**
     * Generate and run fuzzed test cases.
     *
     * Combines predefined edge cases with randomly generated test data to discover
     * unexpected validation behavior and edge cases. Executes edge case tests first
     * (null, booleans, integers, strings of varying lengths, arrays) followed by
     * the specified number of random fuzzed tests. Each test records actual validation
     * results without expectations, capturing crashes and unexpected behavior.
     *
     * @param  PrismTestInterface $prism      Validator instance to test with generated data
     * @param  int                $iterations Number of random fuzzed tests to generate beyond
     *                                        the standard set of edge cases (24 predefined cases)
     * @return TestSuite          Test suite containing all fuzzed test results with
     *                            'fuzzing' group tag and total execution duration
     */
    public function fuzz(PrismTestInterface $prism, int $iterations): TestSuite
    {
        $startTime = microtime(true);
        $results = [];

        // Generate edge case tests
        $edgeCases = $this->generateEdgeCases();

        foreach ($edgeCases as $index => $testData) {
            $results[] = $this->executeTest($prism, $testData, sprintf('edge-case-%d', $index));
        }

        // Generate random fuzzed tests
        for ($i = 0; $i < $iterations; ++$i) {
            $testData = $this->generateRandomData();
            $results[] = $this->executeTest($prism, $testData, sprintf('fuzz-%d', $i));
        }

        $duration = microtime(true) - $startTime;

        return new TestSuite(
            name: sprintf('%s (fuzzed)', $prism->getName()),
            results: $results,
            duration: $duration,
        );
    }

    /**
     * Generate common edge cases for testing.
     *
     * Returns a comprehensive set of edge cases covering boundary conditions:
     * null values, booleans, zero/negative integers, min/max integers, floats,
     * empty/whitespace strings, very long strings (1k and 10k chars), empty arrays,
     * arrays with null/empty values, and nested structures. These cases are designed
     * to stress-test validators with common problematic inputs.
     *
     * @return array<int, mixed> Array of 24 predefined edge case test values
     */
    private function generateEdgeCases(): array
    {
        return [
            null,
            true,
            false,
            0,
            -1,
            1,
            PHP_INT_MAX,
            PHP_INT_MIN,
            0.0,
            -0.0,
            '',
            ' ',
            "\n",
            "\t",
            'a',
            str_repeat('x', 1_000),
            str_repeat('x', 10_000),
            [],
            [null],
            [''],
            [0],
            [[]],
            ['key' => 'value'],
            ['nested' => ['deep' => ['value' => true]]],
        ];
    }

    /**
     * Generate random test data.
     *
     * Randomly selects a data type and generates a value: null, boolean, integer
     * (-1000 to 1000), float (0.1 to 100.0), string (0-100 chars), array (0-10 items),
     * or object (associative array with 0-5 properties). Provides broad coverage of
     * common data structures for property-based testing.
     *
     * @return mixed Random data for testing, type varies per invocation
     */
    private function generateRandomData(): mixed
    {
        $type = random_int(0, 6);

        return match ($type) {
            0 => null,
            1 => (bool) random_int(0, 1),
            2 => random_int(-1_000, 1_000),
            3 => random_int(1, 1_000) / 10.0,
            4 => $this->generateRandomString(),
            5 => $this->generateRandomArray(),
            6 => $this->generateRandomObject(),
        };
    }

    /**
     * Generate random string.
     *
     * Creates a string of random length (0-100 characters) from a character set
     * including uppercase/lowercase letters, digits, common symbols, and spaces.
     * Useful for testing string validation edge cases and boundary conditions.
     *
     * @return string Random string between 0 and 100 characters
     */
    private function generateRandomString(): string
    {
        $length = random_int(0, 100);
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*() ';
        $result = '';

        for ($i = 0; $i < $length; ++$i) {
            $result .= $chars[random_int(0, mb_strlen($chars) - 1)];
        }

        return $result;
    }

    /**
     * Generate random array.
     *
     * Creates an indexed array with 0-10 random scalar elements. Each element
     * is generated using generateRandomScalar() for variety in data types.
     *
     * @return array<int, mixed> Random indexed array with 0-10 scalar values
     */
    private function generateRandomArray(): array
    {
        $length = random_int(0, 10);
        $result = [];

        for ($i = 0; $i < $length; ++$i) {
            $result[] = $this->generateRandomScalar();
        }

        return $result;
    }

    /**
     * Generate random object.
     *
     * Creates an associative array (representing a JSON object) with 0-5 random
     * properties. Property names are selected from a predefined set of common
     * keys (id, name, value, type, status, data, items, nested), and values are
     * random scalars. May contain duplicate keys overwriting previous values.
     *
     * @return array<string, mixed> Random associative array with 0-5 properties
     */
    private function generateRandomObject(): array
    {
        $keys = ['id', 'name', 'value', 'type', 'status', 'data', 'items', 'nested'];
        $length = random_int(0, 5);
        $result = [];

        for ($i = 0; $i < $length; ++$i) {
            $key = $keys[random_int(0, count($keys) - 1)];
            $result[$key] = $this->generateRandomScalar();
        }

        return $result;
    }

    /**
     * Generate random scalar value.
     *
     * Randomly generates a boolean, integer (-100 to 100), float (0.1 to 10.0),
     * or string value. Used as building blocks for arrays and objects in fuzz testing.
     *
     * @return bool|float|int|string Random scalar value of varying type
     */
    private function generateRandomScalar(): bool|int|float|string
    {
        $type = random_int(0, 3);

        return match ($type) {
            0 => (bool) random_int(0, 1),
            1 => random_int(-100, 100),
            2 => random_int(1, 100) / 10.0,
            3 => $this->generateRandomString(),
        };
    }

    /**
     * Execute a single fuzzed test.
     *
     * Runs validator against test data and captures the result or exception.
     * Uses 'true' as the schema (validates everything in JSON Schema) since
     * fuzzing focuses on discovering crashes and unexpected behavior rather
     * than validating against specific schemas. Records both successful
     * validations and exceptions as test results.
     *
     * @param  PrismTestInterface $prism  Validator instance to test
     * @param  mixed              $data   Test data to validate
     * @param  string             $testId Unique test identifier for tracking
     * @return TestResult         Test execution result with timing and outcome data
     */
    private function executeTest(PrismTestInterface $prism, mixed $data, string $testId): TestResult
    {
        $startTime = microtime(true);

        try {
            // Use 'true' as schema - in JSON Schema, true validates everything
            $result = $prism->validate($data, true);
            $duration = microtime(true) - $startTime;
            $actual = $result->isValid();

            // For fuzzing, we don't have expected values, so we just record what happened
            return TestResult::pass(
                id: $testId,
                file: 'fuzzed',
                group: 'fuzzing',
                description: sprintf('Fuzzed test with %s data', $this->describeData($data)),
                data: $data,
                expected: $actual,
                duration: $duration,
            );
        } catch (Throwable $throwable) {
            $duration = microtime(true) - $startTime;

            return TestResult::fail(
                id: $testId,
                file: 'fuzzed',
                group: 'fuzzing',
                description: sprintf('Fuzzed test with %s data', $this->describeData($data)),
                data: $data,
                expected: false,
                actual: false,
                error: $throwable->getMessage(),
                duration: $duration,
                tags: ['fuzzed', 'error'],
            );
        }
    }

    /**
     * Describe data type for logging.
     *
     * Provides human-readable type descriptions for test result output. Distinguishes
     * between empty and non-empty arrays for better diagnostics.
     *
     * @param  mixed  $data Data value to describe
     * @return string Human-readable type description
     */
    private function describeData(mixed $data): string
    {
        if ($data === null) {
            return 'null';
        }

        if (is_bool($data)) {
            return 'boolean';
        }

        if (is_int($data)) {
            return 'integer';
        }

        if (is_string($data)) {
            return 'string';
        }

        if (is_array($data)) {
            return $data === [] ? 'empty array' : 'array';
        }

        return 'unknown';
    }
}
