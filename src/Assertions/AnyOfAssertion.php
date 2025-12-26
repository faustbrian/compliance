<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Assertions;

use Cline\Prism\Contracts\AssertionInterface;

use function array_map;
use function implode;
use function in_array;
use function is_array;
use function sprintf;

/**
 * Assertion that validates if actual result matches any of multiple expected values.
 *
 * Useful for testing validators where multiple outcomes are acceptable,
 * such as edge cases where different validator implementations may differ.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class AnyOfAssertion implements AssertionInterface
{
    /**
     * Execute the assertion against test data.
     *
     * Validates whether the actual result matches the expected result. When the expected
     * value is an array, checks if the actual value is present in that array (any-of semantics).
     * When the expected value is a scalar, performs strict equality comparison.
     *
     * @param  mixed $data     Data being validated (unused but required by interface)
     * @param  mixed $expected Expected validation result, either a boolean or array of acceptable boolean values
     * @param  mixed $actual   Actual validation result from the validator being tested
     * @return bool  True if the actual result matches expected result or is within the expected array
     */
    public function assert(mixed $data, mixed $expected, mixed $actual): bool
    {
        if (!is_array($expected)) {
            return $expected === $actual;
        }

        return in_array($actual, $expected, true);
    }

    /**
     * Get human-readable name for this assertion.
     *
     * @return string The assertion identifier used in test output and reporting
     */
    public function getName(): string
    {
        return 'AnyOf';
    }

    /**
     * Get failure message when assertion fails.
     *
     * Generates a descriptive error message indicating the expected and actual
     * validation results. For array expectations, lists all acceptable values
     * that were not matched.
     *
     * @param  mixed  $data     Data that failed assertion (unused but required by interface)
     * @param  mixed  $expected Expected validation result(s) that were not met
     * @param  mixed  $actual   Actual validation result that caused the failure
     * @return string Descriptive failure message explaining the expectation mismatch
     */
    public function getFailureMessage(mixed $data, mixed $expected, mixed $actual): string
    {
        if (!is_array($expected)) {
            $expected = $expected ? 'valid' : 'invalid';
            $actual = $actual ? 'valid' : 'invalid';

            return sprintf(
                'Expected data to be %s, but validator returned %s',
                $expected,
                $actual,
            );
        }

        $expectedStr = implode(', ', array_map(
            fn ($v): string => $v ? 'valid' : 'invalid',
            $expected,
        ));
        $actual = $actual ? 'valid' : 'invalid';

        return sprintf(
            'Expected data to be one of [%s], but validator returned %s',
            $expectedStr,
            $actual,
        );
    }
}
