<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Contracts;

/**
 * Contract for validation result objects.
 *
 * Represents the outcome of a schema validation operation, providing
 * both a boolean success indicator and detailed error messages for
 * validation failures. Implementations are typically returned by
 * validator classes after processing data against schemas.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ValidationResult
{
    /**
     * Determine if the validation was successful.
     *
     * Returns true if the data passed all validation rules defined in the
     * schema, false otherwise. When this returns false, error details are
     * available via getErrors().
     *
     * @return bool True if validation succeeded, false if validation failed
     */
    public function isValid(): bool;

    /**
     * Get the list of validation error messages.
     *
     * Returns an array of human-readable error messages describing why
     * validation failed. The array is empty when validation succeeds.
     * Each message typically identifies the validation rule that failed
     * and the data path where the failure occurred.
     *
     * @return array<int, string> Array of validation error messages, empty array if validation passed
     */
    public function getErrors(): array;
}
