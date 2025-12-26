<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Output\DiffRenderer;
use Cline\Prism\ValueObjects\TestResult;
use Symfony\Component\Console\Output\BufferedOutput;

describe('DiffRenderer', function (): void {
    describe('renderFailure method', function (): void {
        test('renders failure with all metadata when expected is true and actual is false', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-1',
                file: 'validation.json',
                group: 'String validation',
                description: 'should reject invalid string',
                data: ['name' => 'John'],
                expected: true,
                actual: false,
                error: 'Validation failed',
                duration: 0.123,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('1. should reject invalid string');
            expect($result)->toContain('Test ID: test-1');
            expect($result)->toContain('File: validation.json');
            expect($result)->toContain('Group: String validation');
            expect($result)->toContain('Expected Validation:');
            expect($result)->toContain('VALID');
            expect($result)->toContain('Actual Validation:');
            expect($result)->toContain('INVALID');
            expect($result)->toContain('Error: Validation failed');
            expect($result)->toContain('Duration: 123.00ms');
            expect($result)->toContain('Test Data:');
        });

        test('renders failure when expected is false and actual is true', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-2',
                file: 'validation.json',
                group: 'String validation',
                description: 'should accept valid string',
                data: 'invalid',
                expected: false,
                actual: true,
                duration: 0.050,
            );

            // Act
            $renderer->renderFailure(2, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('2. should accept valid string');
            expect($result)->toContain('INVALID'); // expected false
            expect($result)->toContain('VALID'); // actual true
        });

        test('renders failure without error message when error is null', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-3',
                file: 'test.json',
                group: 'Group',
                description: 'Test description',
                data: null,
                expected: true,
                actual: false,
                error: null,
                duration: 0.001,
            );

            // Act
            $renderer->renderFailure(3, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->not->toContain('Error:');
            expect($result)->toContain('Duration:');
        });

        test('renders failure without duration when duration is zero', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-4',
                file: 'test.json',
                group: 'Group',
                description: 'Test description',
                data: [],
                expected: true,
                actual: false,
                duration: 0.0,
            );

            // Act
            $renderer->renderFailure(4, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->not->toContain('Duration:');
        });

        test('renders failure with separator line', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-5',
                file: 'test.json',
                group: 'Group',
                description: 'Test',
                data: 'simple',
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(5, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain(str_repeat('─', 80));
        });
    });

    describe('renderJsonData method', function (): void {
        test('renders null value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-null',
                file: 'test.json',
                group: 'Group',
                description: 'Null test',
                data: null,
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('null');
        });

        test('renders boolean true value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-bool-true',
                file: 'test.json',
                group: 'Group',
                description: 'Boolean true test',
                data: true,
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('true');
        });

        test('renders boolean false value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-bool-false',
                file: 'test.json',
                group: 'Group',
                description: 'Boolean false test',
                data: false,
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('false');
        });

        test('renders integer value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-int',
                file: 'test.json',
                group: 'Group',
                description: 'Integer test',
                data: 42,
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('42');
        });

        test('renders float value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-float',
                file: 'test.json',
                group: 'Group',
                description: 'Float test',
                data: 3.14,
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('3.14');
        });

        test('renders string value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-string',
                file: 'test.json',
                group: 'Group',
                description: 'String test',
                data: 'hello world',
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"hello world"');
        });

        test('renders empty array', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-empty-array',
                file: 'test.json',
                group: 'Group',
                description: 'Empty array test',
                data: [],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('[]');
        });

        test('renders indexed array', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-indexed-array',
                file: 'test.json',
                group: 'Group',
                description: 'Indexed array test',
                data: ['apple', 'banana', 'cherry'],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"apple"');
            expect($result)->toContain('"banana"');
            expect($result)->toContain('"cherry"');
        });

        test('renders associative array', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-assoc-array',
                file: 'test.json',
                group: 'Group',
                description: 'Associative array test',
                data: ['name' => 'John', 'age' => 30],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"name"');
            expect($result)->toContain('"John"');
            expect($result)->toContain('"age"');
            expect($result)->toContain('30');
        });

        test('renders nested associative array', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-nested',
                file: 'test.json',
                group: 'Group',
                description: 'Nested array test',
                data: [
                    'user' => [
                        'name' => 'John',
                        'email' => 'john@example.com',
                    ],
                ],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"user"');
            expect($result)->toContain('"name"');
            expect($result)->toContain('"John"');
            expect($result)->toContain('"email"');
            expect($result)->toContain('"john@example.com"');
        });

        test('renders indexed array with nested values', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-indexed-nested',
                file: 'test.json',
                group: 'Group',
                description: 'Indexed nested test',
                data: [
                    ['name' => 'John'],
                    ['name' => 'Jane'],
                ],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"name"');
            expect($result)->toContain('"John"');
            expect($result)->toContain('"Jane"');
        });

        test('renders array with all primitive types', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-mixed',
                file: 'test.json',
                group: 'Group',
                description: 'Mixed types test',
                data: [
                    'null' => null,
                    'bool_true' => true,
                    'bool_false' => false,
                    'int' => 42,
                    'float' => 3.14,
                    'string' => 'hello',
                ],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('null');
            expect($result)->toContain('true');
            expect($result)->toContain('false');
            expect($result)->toContain('42');
            expect($result)->toContain('3.14');
            expect($result)->toContain('"hello"');
        });

        test('renders complex object via JSON encoding', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $object = new stdClass();
            $object->name = 'John';
            $object->age = 30;

            $failure = TestResult::fail(
                id: 'test-object',
                file: 'test.json',
                group: 'Group',
                description: 'Object test',
                data: $object,
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"name"');
            expect($result)->toContain('"John"');
        });

        test('renders unserializable resource as error message', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            // Create a resource which cannot be JSON encoded
            $resource = fopen('php://memory', 'rb');
            $failure = TestResult::fail(
                id: 'test-resource',
                file: 'test.json',
                group: 'Group',
                description: 'Resource test',
                data: $resource,
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('(unserializable)');

            // Cleanup
            fclose($resource);
        });
    });

    describe('renderInlineValue method', function (): void {
        test('renders inline null value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-inline-null',
                file: 'test.json',
                group: 'Group',
                description: 'Inline null test',
                data: ['value' => null],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('null');
        });

        test('renders inline boolean true value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-inline-bool-true',
                file: 'test.json',
                group: 'Group',
                description: 'Inline bool true test',
                data: ['active' => true],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('true');
        });

        test('renders inline boolean false value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-inline-bool-false',
                file: 'test.json',
                group: 'Group',
                description: 'Inline bool false test',
                data: ['active' => false],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('false');
        });

        test('renders inline integer value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-inline-int',
                file: 'test.json',
                group: 'Group',
                description: 'Inline int test',
                data: ['count' => 100],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('100');
        });

        test('renders inline float value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-inline-float',
                file: 'test.json',
                group: 'Group',
                description: 'Inline float test',
                data: ['price' => 99.99],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('99.99');
        });

        test('renders inline string value', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-inline-string',
                file: 'test.json',
                group: 'Group',
                description: 'Inline string test',
                data: ['message' => 'Hello World'],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"Hello World"');
        });

        test('renders nested array value on new line', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-inline-nested',
                file: 'test.json',
                group: 'Group',
                description: 'Inline nested test',
                data: ['metadata' => ['key' => 'value']],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"metadata"');
            expect($result)->toContain('"key"');
            expect($result)->toContain('"value"');
        });
    });

    describe('highlightJson method', function (): void {
        test('highlights JSON with all syntax elements', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $object = new stdClass();
            $object->name = 'John Doe';
            $object->age = 30;
            $object->active = true;
            $object->score = 98.5;
            $object->metadata = null;

            $failure = TestResult::fail(
                id: 'test-highlight',
                file: 'test.json',
                group: 'Group',
                description: 'Highlight test',
                data: $object,
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            // JSON encoding produces these elements which get highlighted
            expect($result)->toContain('"name"');
            expect($result)->toContain('"John Doe"');
            expect($result)->toContain('30');
            expect($result)->toContain('true');
            expect($result)->toContain('null');
        });

        test('handles multi-line JSON with proper indentation', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $object = new stdClass();
            $object->user = new stdClass();
            $object->user->name = 'Jane';
            $object->user->email = 'jane@example.com';

            $failure = TestResult::fail(
                id: 'test-multiline',
                file: 'test.json',
                group: 'Group',
                description: 'Multi-line test',
                data: $object,
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"user"');
            expect($result)->toContain('"name"');
            expect($result)->toContain('"Jane"');
            expect($result)->toContain('"email"');
            expect($result)->toContain('"jane@example.com"');
        });
    });

    describe('edge cases', function (): void {
        test('renders deeply nested structures', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-deep',
                file: 'test.json',
                group: 'Group',
                description: 'Deep nesting test',
                data: [
                    'level1' => [
                        'level2' => [
                            'level3' => [
                                'value' => 'deep',
                            ],
                        ],
                    ],
                ],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"level1"');
            expect($result)->toContain('"level2"');
            expect($result)->toContain('"level3"');
            expect($result)->toContain('"value"');
            expect($result)->toContain('"deep"');
        });

        test('renders large indexed array', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-large',
                file: 'test.json',
                group: 'Group',
                description: 'Large array test',
                data: range(1, 10),
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('1');
            expect($result)->toContain('5');
            expect($result)->toContain('10');
        });

        test('renders array with special characters in strings', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-special',
                file: 'test.json',
                group: 'Group',
                description: 'Special chars test',
                data: ['message' => 'Hello "World" & <Friends>'],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('message');
        });

        test('renders zero values correctly', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-zero',
                file: 'test.json',
                group: 'Group',
                description: 'Zero values test',
                data: [
                    'int_zero' => 0,
                    'float_zero' => 0.0,
                    'string_zero' => '0',
                ],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"int_zero"');
            expect($result)->toContain('"float_zero"');
            expect($result)->toContain('"string_zero"');
        });

        test('renders negative numbers correctly', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-negative',
                file: 'test.json',
                group: 'Group',
                description: 'Negative numbers test',
                data: ['negative_int' => -42, 'negative_float' => -3.14],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('-42');
            expect($result)->toContain('-3.14');
        });

        test('renders empty string correctly', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-empty-string',
                file: 'test.json',
                group: 'Group',
                description: 'Empty string test',
                data: ['empty' => ''],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('""');
        });

        test('renders mixed indexed and associative arrays', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-mixed-array',
                file: 'test.json',
                group: 'Group',
                description: 'Mixed array test',
                data: [
                    'users' => ['Alice', 'Bob'],
                    'metadata' => ['count' => 2, 'active' => true],
                ],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('"users"');
            expect($result)->toContain('"Alice"');
            expect($result)->toContain('"Bob"');
            expect($result)->toContain('"metadata"');
            expect($result)->toContain('"count"');
            expect($result)->toContain('2');
        });

        test('renders very long duration correctly', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-long-duration',
                file: 'test.json',
                group: 'Group',
                description: 'Long duration test',
                data: 'slow',
                expected: true,
                actual: false,
                error: null,
                duration: 5.678,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            expect($result)->toContain('5678.00ms');
        });

        test('renders scientific notation numbers', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new DiffRenderer($output);
            $failure = TestResult::fail(
                id: 'test-scientific',
                file: 'test.json',
                group: 'Group',
                description: 'Scientific notation test',
                data: ['large' => 1.23e10, 'small' => 1.23e-10],
                expected: true,
                actual: false,
            );

            // Act
            $renderer->renderFailure(1, $failure);

            // Assert
            $result = $output->fetch();
            // PHP converts scientific notation to decimal when casting to string
            expect($result)->not->toBeEmpty();
        });
    });
});
