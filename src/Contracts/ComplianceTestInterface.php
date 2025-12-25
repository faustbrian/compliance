<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\Contracts;

/**
 * Contract for compliance test implementations.
 *
 * Defines the interface for running compliance validation tests against
 * specification drafts such as JSON Schema versions. Implementations provide
 * the test discovery mechanism, validation logic, and JSON decoding strategy
 * specific to each draft or standard being tested.
 */
interface ComplianceTestInterface
{
    /**
     * Get the identifier name for this draft or specification version.
     *
     * Used for filtering tests by draft and labeling test suite results.
     * Examples: "draft-04", "draft-07", "2020-12".
     *
     * @return string The draft or version identifier
     */
    public function getName(): string;

    /**
     * Get the fully-qualified validator class name for this draft.
     *
     * Returns the class name of the validator implementation responsible for
     * validating data against schemas according to this draft's specification.
     *
     * @return class-string The validator class name
     */
    public function getValidatorClass(): string;

    /**
     * Get the absolute directory path containing test files.
     *
     * Returns the file system path where compliance test JSON files are stored
     * for this draft. Test files typically contain test cases with schemas,
     * data, and expected validation results.
     *
     * @return string The absolute path to the test directory
     */
    public function getTestDirectory(): string;

    /**
     * Validate data against a schema using this draft's validation rules.
     *
     * Executes validation logic specific to this draft, returning a result
     * object that indicates success or failure along with any error messages.
     *
     * @param  mixed            $data   The data to validate against the schema
     * @param  mixed            $schema The schema definition to validate against
     * @return ValidationResult The validation result with success status and errors
     */
    public function validate(mixed $data, mixed $schema): ValidationResult;

    /**
     * Get glob patterns for discovering test files in the test directory.
     *
     * Returns an array of glob patterns used to locate test case files.
     * Common patterns include "*.json" or "*\/*\/*.json" for recursive scanning.
     *
     * @return array<int, string> Array of glob patterns for test file discovery
     */
    public function getTestFilePatterns(): array;

    /**
     * Decode JSON string preserving exact type information.
     *
     * Parses JSON while maintaining critical type distinctions such as
     * empty object {} versus empty array [], which are semantically different
     * in schema validation contexts. Uses draft-specific decoding logic.
     *
     * @param  string $json The JSON string to decode
     * @return mixed  The decoded value with preserved type information
     */
    public function decodeJson(string $json): mixed;

    /**
     * Determine if a test file should be included in this test suite.
     *
     * Allows implementations to filter test files based on path patterns,
     * enabling separation of required tests from optional tests or excluding
     * specific subdirectories from test discovery.
     *
     * @param  string $filePath The absolute path to the test file
     * @return bool   True if the file should be included, false to exclude it
     */
    public function shouldIncludeFile(string $filePath): bool;
}
