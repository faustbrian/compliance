<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use function gettype;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_encode;
use function sprintf;

/**
 * Service for generating human-readable diffs of JSON data structures.
 *
 * Provides comparison between expected and actual validation results,
 * highlighting differences in data types, values, and structure. Useful
 * for debugging test failures and understanding validation mismatches.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class JsonDiffService
{
    /**
     * Generate a human-readable diff of two JSON values.
     *
     * Compares expected and actual values and produces formatted output highlighting
     * differences. Handles type mismatches, primitive value differences, and complex
     * data structure comparisons. Output format varies based on the nature of the difference.
     *
     * @param  mixed  $expected Expected value from test specification
     * @param  mixed  $actual   Actual value from validation result
     * @return string Formatted diff message describing the difference, or confirmation
     *                if values are identical
     */
    public function diff(mixed $expected, mixed $actual): string
    {
        // If values are identical, show simple message
        if ($expected === $actual) {
            return 'Values are identical';
        }

        // Type mismatch - highlight it
        if (gettype($expected) !== gettype($actual)) {
            return sprintf(
                "Type mismatch:\n  Expected: %s (%s)\n  Actual: %s (%s)",
                $this->formatType($expected),
                $this->formatValue($expected),
                $this->formatType($actual),
                $this->formatValue($actual),
            );
        }

        // For primitives, show the difference
        if ($this->isPrimitive($expected)) {
            return sprintf(
                "Value mismatch:\n  Expected: %s\n  Actual: %s",
                $this->formatValue($expected),
                $this->formatValue($actual),
            );
        }

        // For complex types, show JSON representation
        return sprintf(
            "Data structure mismatch:\n\nExpected:\n%s\n\nActual:\n%s",
            $this->formatJson($expected),
            $this->formatJson($actual),
        );
    }

    /**
     * Check if a value is a primitive type.
     *
     * Determines whether the value is null, boolean, integer, float, or string.
     * Arrays and objects are not considered primitive types.
     *
     * @param  mixed $value Value to check
     * @return bool  True if primitive type, false otherwise
     */
    private function isPrimitive(mixed $value): bool
    {
        return null === $value || is_bool($value) || is_int($value) || is_float($value) || is_string($value);
    }

    /**
     * Get human-readable type name for a value.
     *
     * Converts PHP type information into user-friendly type names suitable
     * for display in error messages. Maps PHP types to common terminology.
     *
     * @param  mixed  $value Value to get type name for
     * @return string Human-readable type name (null|boolean|integer|float|string|array|object)
     */
    private function formatType(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_string($value)) {
            return 'string';
        }

        if (is_array($value)) {
            return 'array';
        }

        return 'object';
    }

    /**
     * Format value for display in diff output.
     *
     * Converts values into readable string representations suitable for displaying
     * in comparison messages. Handles null, booleans, strings, numbers, and complex
     * types with appropriate formatting.
     *
     * @param  mixed  $value Value to format
     * @return string Formatted string representation of the value
     */
    private function formatValue(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return sprintf('"%s"', $value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->formatJson($value);
    }

    /**
     * Format value as pretty-printed JSON.
     *
     * Encodes the value as JSON with formatting flags for human readability,
     * including pretty printing, unescaped Unicode, and unescaped slashes.
     *
     * @param  mixed  $value Value to encode as JSON
     * @return string Pretty-printed JSON string, or error message if encoding fails
     */
    private function formatJson(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'Unable to encode';
    }
}
