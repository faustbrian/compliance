<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\Contracts;

interface ComplianceTestInterface
{
    /**
     * Get the name of this draft/version being tested.
     */
    public function getName(): string;

    /**
     * Get the validator class for this draft.
     *
     * @return class-string
     */
    public function getValidatorClass(): string;

    /**
     * Get the directory containing test files for this draft.
     */
    public function getTestDirectory(): string;

    /**
     * Validate data against a schema.
     */
    public function validate(mixed $data, mixed $schema): ValidationResult;

    /**
     * Get glob patterns for finding test files.
     *
     * @return array<int, string>
     */
    public function getTestFilePatterns(): array;

    /**
     * Decode JSON string preserving type distinctions (e.g., {} vs []).
     */
    public function decodeJson(string $json): mixed;
}
