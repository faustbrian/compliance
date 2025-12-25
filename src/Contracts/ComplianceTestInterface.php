<?php

declare(strict_types=1);

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
     *
     * @param  mixed  $data
     * @param  mixed  $schema
     * @return ValidationResult
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
     *
     * @return mixed
     */
    public function decodeJson(string $json): mixed;
}
