<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Contracts;

/**
 * Contract for custom assertion implementations.
 *
 * Allows defining reusable validation logic beyond simple expected/actual
 * comparison, enabling complex assertions and semantic test messages.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface AssertionInterface
{
    /**
     * Execute the assertion against test data.
     *
     * Determines whether the actual validation result satisfies the assertion
     * criteria given the expected result. Implementations define custom logic
     * for comparing expected and actual validation outcomes.
     *
     * @param  mixed $data     Data being validated
     * @param  mixed $expected Expected validation result or criteria
     * @param  mixed $actual   Actual validation result from validator
     * @return bool  True if assertion passes, false otherwise
     */
    public function assert(mixed $data, mixed $expected, mixed $actual): bool;

    /**
     * Get human-readable name for this assertion.
     *
     * Returns an identifier used in test output and reporting to indicate
     * which assertion strategy was applied to a test case.
     *
     * @return string The assertion name (e.g., "StrictEquality", "AnyOf")
     */
    public function getName(): string;

    /**
     * Get failure message when assertion fails.
     *
     * Generates a descriptive error message explaining why the assertion failed,
     * including details about the expected criteria and the actual result that
     * did not meet those criteria.
     *
     * @param  mixed  $data     Data that failed assertion
     * @param  mixed  $expected Expected validation result or criteria
     * @param  mixed  $actual   Actual validation result that caused failure
     * @return string Descriptive failure message for test output
     */
    public function getFailureMessage(mixed $data, mixed $expected, mixed $actual): string;
}
