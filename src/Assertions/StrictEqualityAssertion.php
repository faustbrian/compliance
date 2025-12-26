<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Assertions;

use Cline\Prism\Contracts\AssertionInterface;

use function sprintf;

/**
 * Assertion that validates strict equality between expected and actual results.
 *
 * The default assertion used by the test framework. Ensures the validator's
 * actual validation result exactly matches the expected result.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class StrictEqualityAssertion implements AssertionInterface
{
    /**
     * Execute the assertion against test data.
     *
     * Performs strict equality comparison (===) between the expected and actual
     * validation results. This is the default assertion that requires exact match
     * between what the test expects and what the validator produces.
     *
     * @param  mixed $data     Data being validated (unused but required by interface)
     * @param  mixed $expected Expected validation result, typically a boolean
     * @param  mixed $actual   Actual validation result from the validator being tested
     * @return bool  True if expected and actual results are strictly equal, false otherwise
     */
    public function assert(mixed $data, mixed $expected, mixed $actual): bool
    {
        return $expected === $actual;
    }

    /**
     * Get human-readable name for this assertion.
     *
     * @return string The assertion identifier used in test output and reporting
     */
    public function getName(): string
    {
        return 'StrictEquality';
    }

    /**
     * Get failure message when assertion fails.
     *
     * Generates a descriptive error message indicating that the validator's
     * actual result did not strictly match the expected result.
     *
     * @param  mixed  $data     Data that failed assertion (unused but required by interface)
     * @param  mixed  $expected Expected validation result that was not met
     * @param  mixed  $actual   Actual validation result that caused the failure
     * @return string Descriptive failure message explaining the expectation mismatch
     */
    public function getFailureMessage(mixed $data, mixed $expected, mixed $actual): string
    {
        $expected = $expected ? 'valid' : 'invalid';
        $actual = $actual ? 'valid' : 'invalid';

        return sprintf(
            'Expected data to be %s, but validator returned %s',
            $expected,
            $actual,
        );
    }
}
