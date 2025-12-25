<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Output\JsonRenderer;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;
use Symfony\Component\Console\Output\BufferedOutput;

describe('JsonRenderer', function (): void {
    describe('render method', function (): void {
        test('renders valid JSON output structure', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);
            $suite = new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
                ],
                duration: 1.5,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data)->toHaveKeys(['summary', 'suites']);
        });

        test('calculates summary statistics correctly for all passing tests', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);
            $suite = new TestSuite(
                name: 'Perfect Suite',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
                    TestResult::pass('t3', 'f1', 'g1', 'd3', [], true),
                ],
                duration: 2.5,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['summary'])->toBe([
                'total' => 3,
                'passed' => 3,
                'failed' => 0,
                'pass_rate' => 100,
                'duration' => 2.5,
            ]);
        });

        test('calculates summary statistics correctly with failures', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);
            $suite = new TestSuite(
                name: 'Mixed Suite',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::fail('t2', 'f1', 'g1', 'd2', [], true, false),
                    TestResult::pass('t3', 'f1', 'g1', 'd3', [], true),
                ],
                duration: 1.234,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['summary'])->toBe([
                'total' => 3,
                'passed' => 2,
                'failed' => 1,
                'pass_rate' => 66.7,
                'duration' => 1.23,
            ]);
        });

        test('calculates summary statistics for multiple suites', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);
            $suites = [
                new TestSuite(
                    name: 'Suite 1',
                    results: [
                        TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                        TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
                    ],
                    duration: 1.5,
                ),
                new TestSuite(
                    name: 'Suite 2',
                    results: [
                        TestResult::pass('t3', 'f2', 'g2', 'd3', [], true),
                        TestResult::fail('t4', 'f2', 'g2', 'd4', [], true, false),
                    ],
                    duration: 2.5,
                ),
            ];

            // Act
            $renderer->render($suites);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['summary'])->toBe([
                'total' => 4,
                'passed' => 3,
                'failed' => 1,
                'pass_rate' => 75,
                'duration' => 4,
            ]);
        });

        test('handles empty suite array', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);

            // Act
            $renderer->render([]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['summary'])->toBe([
                'total' => 0,
                'passed' => 0,
                'failed' => 0,
                'pass_rate' => 0,
                'duration' => 0,
            ])
                ->and($data['suites'])->toBe([]);
        });

        test('handles suite with zero tests', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);
            $suite = new TestSuite(
                name: 'Empty Suite',
                results: [],
                duration: 0.5,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['summary'])->toBe([
                'total' => 0,
                'passed' => 0,
                'failed' => 0,
                'pass_rate' => 0,
                'duration' => 0.5,
            ]);
        });

        test('rounds pass_rate to one decimal place', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);
            $suite = new TestSuite(
                name: 'Rounding Suite',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::fail('t2', 'f1', 'g1', 'd2', [], true, false),
                    TestResult::fail('t3', 'f1', 'g1', 'd3', [], true, false),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            // 1/3 * 100 = 33.333... should round to 33.3
            expect($data['summary']['pass_rate'])->toBe(33.3);
        });

        test('rounds duration to two decimal places', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);
            $suite = new TestSuite(
                name: 'Duration Suite',
                results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                duration: 1.234_567_89,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['summary']['duration'])->toBe(1.23);
        });

        test('does not include failures when includeFailures is false', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output, includeFailures: false);
            $suite = new TestSuite(
                name: 'Suite with Failures',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', ['key' => 'value'], true),
                    TestResult::fail('t2', 'f2', 'g2', 'd2', ['data' => 'test'], true, false, 'Error message'),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['suites'][0])->not->toHaveKey('failures');
        });

        test('includes failures when includeFailures is true and failures exist', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'Suite with Failures',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', ['key' => 'value'], true),
                    TestResult::fail('t2', 'f2', 'g2', 'd2', ['data' => 'test'], true, false, 'Error message'),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['suites'][0])->toHaveKey('failures')
                ->and($data['suites'][0]['failures'])->toHaveCount(1)
                ->and($data['suites'][0]['failures'][0])->toBe([
                    'id' => 't2',
                    'file' => 'f2',
                    'group' => 'g2',
                    'description' => 'd2',
                    'expected_valid' => true,
                    'actual_valid' => false,
                    'error' => 'Error message',
                    'data' => ['data' => 'test'],
                ]);
        });

        test('does not include failures key when includeFailures is true but no failures exist', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'All Passing',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['suites'][0])->not->toHaveKey('failures');
        });

        test('outputs properly formatted JSON with pretty print', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);
            $suite = new TestSuite(
                name: 'Test Suite',
                results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();

            // Pretty printed JSON should have newlines and indentation
            expect($json)->toContain("\n")
                ->and($json)->toContain('    ');
        });
    });

    describe('formatSuite method', function (): void {
        test('formats suite with basic information', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);
            $suite = new TestSuite(
                name: 'Sample Suite',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
                ],
                duration: 1.5,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['suites'][0])->toBe([
                'name' => 'Sample Suite',
                'total' => 2,
                'passed' => 2,
                'failed' => 0,
                'pass_rate' => 100,
                'duration' => 1.5,
            ]);
        });

        test('formats suite with mixed results', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);
            $suite = new TestSuite(
                name: 'Mixed Suite',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::fail('t2', 'f1', 'g1', 'd2', [], true, false),
                    TestResult::fail('t3', 'f1', 'g1', 'd3', [], true, false),
                    TestResult::pass('t4', 'f1', 'g1', 'd4', [], true),
                ],
                duration: 2.345_6,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['suites'][0])->toBe([
                'name' => 'Mixed Suite',
                'total' => 4,
                'passed' => 2,
                'failed' => 2,
                'pass_rate' => 50,
                'duration' => 2.35,
            ]);
        });

        test('includes failure details when includeFailures is true', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'Failed Suite',
                results: [
                    TestResult::fail(
                        id: 'test-1',
                        file: 'tests/file1.php',
                        group: 'Validation',
                        description: 'Should validate email',
                        data: ['email' => 'invalid'],
                        expectedValid: true,
                        actualValid: false,
                        error: 'Invalid email format',
                    ),
                    TestResult::fail(
                        id: 'test-2',
                        file: 'tests/file2.php',
                        group: 'Authentication',
                        description: 'Should authenticate user',
                        data: ['username' => 'test', 'password' => 'wrong'],
                        expectedValid: true,
                        actualValid: false,
                        error: 'Authentication failed',
                    ),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['suites'][0]['failures'])->toHaveCount(2)
                ->and($data['suites'][0]['failures'][0])->toBe([
                    'id' => 'test-1',
                    'file' => 'tests/file1.php',
                    'group' => 'Validation',
                    'description' => 'Should validate email',
                    'expected_valid' => true,
                    'actual_valid' => false,
                    'error' => 'Invalid email format',
                    'data' => ['email' => 'invalid'],
                ])
                ->and($data['suites'][0]['failures'][1])->toBe([
                    'id' => 'test-2',
                    'file' => 'tests/file2.php',
                    'group' => 'Authentication',
                    'description' => 'Should authenticate user',
                    'expected_valid' => true,
                    'actual_valid' => false,
                    'error' => 'Authentication failed',
                    'data' => ['username' => 'test', 'password' => 'wrong'],
                ]);
        });

        test('formats multiple suites correctly', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output);
            $suites = [
                new TestSuite(
                    name: 'First Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
                new TestSuite(
                    name: 'Second Suite',
                    results: [TestResult::fail('t2', 'f2', 'g2', 'd2', [], true, false)],
                    duration: 2.0,
                ),
                new TestSuite(
                    name: 'Third Suite',
                    results: [
                        TestResult::pass('t3', 'f3', 'g3', 'd3', [], true),
                        TestResult::pass('t4', 'f3', 'g3', 'd4', [], true),
                    ],
                    duration: 3.0,
                ),
            ];

            // Act
            $renderer->render($suites);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['suites'])->toHaveCount(3)
                ->and($data['suites'][0]['name'])->toBe('First Suite')
                ->and($data['suites'][1]['name'])->toBe('Second Suite')
                ->and($data['suites'][2]['name'])->toBe('Third Suite');
        });

        test('handles null error in failure details', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'Failed Suite',
                results: [
                    TestResult::fail(
                        id: 'test-1',
                        file: 'tests/file1.php',
                        group: 'Validation',
                        description: 'Should fail',
                        data: [],
                        expectedValid: true,
                        actualValid: false,
                    ),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['suites'][0]['failures'][0]['error'])->toBeNull();
        });

        test('handles complex data structures in failure details', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new JsonRenderer($output, includeFailures: true);
            $complexData = [
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'roles' => ['admin', 'editor'],
                ],
                'nested' => [
                    'level1' => [
                        'level2' => [
                            'value' => 123,
                        ],
                    ],
                ],
            ];

            $suite = new TestSuite(
                name: 'Complex Data Suite',
                results: [
                    TestResult::fail(
                        id: 'test-1',
                        file: 'tests/file1.php',
                        group: 'Complex',
                        description: 'Complex data test',
                        data: $complexData,
                        expectedValid: true,
                        actualValid: false,
                        error: 'Complex validation failed',
                    ),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);

            // Assert
            $json = $output->fetch();
            $data = json_decode($json, true);

            expect($data['suites'][0]['failures'][0]['data'])->toBe($complexData);
        });
    });
});
