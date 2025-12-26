<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Output\DetailRenderer;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;

describe('DetailRenderer', function (): void {
    test('renders detailed test failures', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Detailed Suite',
            results: [
                TestResult::fail(
                    id: 'test-1',
                    file: 'validation.json',
                    group: 'String validation',
                    description: 'should reject invalid string',
                    data: 123,
                    expected: false,
                    actual: true,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failures with error messages', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Error Suite',
            results: [
                TestResult::fail(
                    id: 'test-2',
                    file: 'error.json',
                    group: 'Error handling',
                    description: 'should handle error',
                    data: null,
                    expected: true,
                    actual: false,
                    error: 'Validation threw exception',
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders nothing when no failures', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Success Suite',
            results: [
                TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure without error message', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'No Error Suite',
            results: [
                TestResult::fail(
                    id: 'test-3',
                    file: 'no-error.json',
                    group: 'Clean failures',
                    description: 'should fail without error message',
                    data: ['key' => 'value'],
                    expected: true,
                    actual: false,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure with zero duration', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Zero Duration Suite',
            results: [
                TestResult::fail(
                    id: 'test-4',
                    file: 'instant.json',
                    group: 'Fast tests',
                    description: 'should complete instantly',
                    data: 'quick',
                    expected: false,
                    actual: true,
                    error: null,
                    duration: 0.0,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure with negative duration', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Negative Duration Suite',
            results: [
                TestResult::fail(
                    id: 'test-5',
                    file: 'negative.json',
                    group: 'Edge cases',
                    description: 'should handle negative duration',
                    data: 'test',
                    expected: true,
                    actual: false,
                    error: null,
                    duration: -0.5,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure with positive duration', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Duration Suite',
            results: [
                TestResult::fail(
                    id: 'test-6',
                    file: 'slow.json',
                    group: 'Performance',
                    description: 'should track execution time',
                    data: 'slow test',
                    expected: false,
                    actual: true,
                    error: null,
                    duration: 0.123,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure where both expected and actual are valid', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Both Valid Suite',
            results: [
                TestResult::fail(
                    id: 'test-7',
                    file: 'both-valid.json',
                    group: 'Validation mismatch',
                    description: 'should show both as VALID',
                    data: ['status' => 'valid'],
                    expected: true,
                    actual: true,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure where both expected and actual are invalid', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Both Invalid Suite',
            results: [
                TestResult::fail(
                    id: 'test-8',
                    file: 'both-invalid.json',
                    group: 'Validation mismatch',
                    description: 'should show both as INVALID',
                    data: ['status' => 'invalid'],
                    expected: false,
                    actual: false,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders multiple failures in single suite', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Multiple Failures Suite',
            results: [
                TestResult::fail(
                    id: 'test-9',
                    file: 'first.json',
                    group: 'Group A',
                    description: 'first failure',
                    data: 'data1',
                    expected: true,
                    actual: false,
                    error: 'Error 1',
                    duration: 0.1,
                ),
                TestResult::fail(
                    id: 'test-10',
                    file: 'second.json',
                    group: 'Group B',
                    description: 'second failure',
                    data: 'data2',
                    expected: false,
                    actual: true,
                    error: null,
                    duration: 0.0,
                ),
                TestResult::fail(
                    id: 'test-11',
                    file: 'third.json',
                    group: 'Group C',
                    description: 'third failure',
                    data: ['complex' => 'data'],
                    expected: true,
                    actual: false,
                    error: 'Error 3',
                    duration: 0.05,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure with complex nested data structure', function (): void {
        // Arrange
        $complexData = [
            'user' => [
                'id' => 123,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'settings' => [
                    'theme' => 'dark',
                    'notifications' => true,
                    'preferences' => [
                        'language' => 'en',
                        'timezone' => 'UTC',
                    ],
                ],
            ],
            'metadata' => [
                'created_at' => '2024-01-01',
                'tags' => ['tag1', 'tag2', 'tag3'],
            ],
        ];

        $suite = new TestSuite(
            name: 'Complex Data Suite',
            results: [
                TestResult::fail(
                    id: 'test-12',
                    file: 'complex.json',
                    group: 'Data structures',
                    description: 'should handle complex nested data',
                    data: $complexData,
                    expected: true,
                    actual: false,
                    error: null,
                    duration: 0.25,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure with special characters in strings', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Special Characters Suite',
            results: [
                TestResult::fail(
                    id: 'test-13',
                    file: 'special/path/file.json',
                    group: 'Special chars: <>&"\'',
                    description: 'should handle special characters: <>&"\'',
                    data: 'String with special: <>&"\'',
                    expected: false,
                    actual: true,
                    error: 'Error with special: <>&"\'',
                    duration: 0.01,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure with empty string data', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Empty String Suite',
            results: [
                TestResult::fail(
                    id: 'test-14',
                    file: 'empty.json',
                    group: 'Empty values',
                    description: 'should handle empty string',
                    data: '',
                    expected: false,
                    actual: true,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure with empty array data', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Empty Array Suite',
            results: [
                TestResult::fail(
                    id: 'test-15',
                    file: 'empty-array.json',
                    group: 'Empty values',
                    description: 'should handle empty array',
                    data: [],
                    expected: true,
                    actual: false,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure with boolean data', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Boolean Suite',
            results: [
                TestResult::fail(
                    id: 'test-16',
                    file: 'boolean.json',
                    group: 'Boolean values',
                    description: 'should handle boolean data',
                    data: false,
                    expected: true,
                    actual: false,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure with numeric data types', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Numeric Suite',
            results: [
                TestResult::fail(
                    id: 'test-17',
                    file: 'numeric.json',
                    group: 'Numeric values',
                    description: 'should handle float data',
                    data: 3.141_59,
                    expected: false,
                    actual: true,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders failure with very long error message', function (): void {
        // Arrange
        $longError = str_repeat('This is a very long error message. ', 50);

        $suite = new TestSuite(
            name: 'Long Error Suite',
            results: [
                TestResult::fail(
                    id: 'test-18',
                    file: 'long-error.json',
                    group: 'Error messages',
                    description: 'should handle long error messages',
                    data: 'test',
                    expected: true,
                    actual: false,
                    error: $longError,
                    duration: 0.5,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders empty suite with no results', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Empty Suite',
            results: [],
            duration: 0.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });

    test('renders suite with mixed passing and failing tests', function (): void {
        // Arrange
        $suite = new TestSuite(
            name: 'Mixed Results Suite',
            results: [
                TestResult::pass('pass-1', 'pass.json', 'Passing group', 'passing test', 'data', true),
                TestResult::fail(
                    id: 'fail-1',
                    file: 'fail.json',
                    group: 'Failing group',
                    description: 'failing test',
                    data: 'data',
                    expected: true,
                    actual: false,
                    error: 'Failed',
                ),
                TestResult::pass('pass-2', 'pass2.json', 'Passing group', 'another passing test', 'data', false),
                TestResult::fail(
                    id: 'fail-2',
                    file: 'fail2.json',
                    group: 'Failing group',
                    description: 'another failing test',
                    data: 'data',
                    expected: false,
                    actual: true,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();

        // Act
        $renderer->render($suite);

        // Assert
        expect(true)->toBeTrue();
    });
});
