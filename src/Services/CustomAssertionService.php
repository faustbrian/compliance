<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use Cline\Prism\Assertions\StrictEqualityAssertion;
use Cline\Prism\Contracts\AssertionInterface;

use function array_key_exists;
use function array_keys;

/**
 * Service for managing and executing custom assertion logic.
 *
 * Provides a registry of custom assertions that can be used instead of
 * the default strict equality check, enabling complex validation rules.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class CustomAssertionService
{
    /**
     * Create new custom assertion service.
     *
     * @param array<string, AssertionInterface> $assertions Custom assertions indexed by unique names,
     *                                                      allowing registration of domain-specific validation
     *                                                      rules beyond strict equality checks. Each assertion
     *                                                      must implement AssertionInterface with assert() and
     *                                                      getFailureMessage() methods. Empty array defaults to
     *                                                      using StrictEqualityAssertion for all validations.
     */
    public function __construct(
        private array $assertions = [],
    ) {}

    /**
     * Execute assertion for test data.
     *
     * Runs the specified custom assertion or falls back to strict equality comparison.
     * Returns both the assertion result (pass/fail) and an optional failure message
     * for detailed diagnostics when assertions fail.
     *
     * @param  null|string                               $assertionName Name of registered assertion to execute. When null
     *                                                                  or not found, defaults to StrictEqualityAssertion
     * @param  mixed                                     $data          Test data being validated
     * @param  mixed                                     $expected      Expected validation outcome from test specification
     * @param  mixed                                     $actual        Actual validation outcome from validator execution
     * @return array{passed: bool, message: null|string} Assertion result with pass/fail status and
     *                                                   optional failure message for diagnostics
     */
    public function execute(?string $assertionName, mixed $data, mixed $expected, mixed $actual): array
    {
        $assertion = $this->getAssertion($assertionName);

        $passed = $assertion->assert($data, $expected, $actual);
        $message = $passed ? null : $assertion->getFailureMessage($data, $expected, $actual);

        return [
            'passed' => $passed,
            'message' => $message,
        ];
    }

    /**
     * Check if assertion is registered.
     *
     * @param  string $name Assertion name to check
     * @return bool   True if the assertion exists in the registry
     */
    public function hasAssertion(string $name): bool
    {
        return array_key_exists($name, $this->assertions);
    }

    /**
     * Get all registered assertion names.
     *
     * @return array<int, string> List of assertion names
     */
    public function getAssertionNames(): array
    {
        return array_keys($this->assertions);
    }

    /**
     * Get assertion by name.
     *
     * Retrieves a registered assertion by name or returns the default strict
     * equality assertion. Always returns a valid AssertionInterface instance,
     * falling back to StrictEqualityAssertion when the requested assertion
     * doesn't exist.
     *
     * @param  null|string        $name Assertion name to retrieve. Null or unregistered
     *                                  names return StrictEqualityAssertion
     * @return AssertionInterface Assertion instance ready for execution
     */
    private function getAssertion(?string $name): AssertionInterface
    {
        if ($name === null) {
            return new StrictEqualityAssertion();
        }

        if (array_key_exists($name, $this->assertions)) {
            return $this->assertions[$name];
        }

        return new StrictEqualityAssertion();
    }
}
