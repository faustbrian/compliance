<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Compliance\Output\YamlRenderer;
use Cline\Compliance\ValueObjects\TestResult;
use Cline\Compliance\ValueObjects\TestSuite;
use Symfony\Component\Console\Output\BufferedOutput;

describe('YamlRenderer', function (): void {
    describe('render method', function (): void {
        test('renders basic YAML structure with summary and suites', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suite = new TestSuite(
                name: 'Basic Suite',
                results: [
                    TestResult::pass('t1', 'file1.json', 'group1', 'test description', ['key' => 'value'], true),
                    TestResult::pass('t2', 'file1.json', 'group1', 'another test', ['foo' => 'bar'], true),
                ],
                duration: 1.5,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('summary:')
                ->toContain('total:')
                ->toContain('passed:')
                ->toContain('failed:')
                ->toContain('pass_rate:')
                ->toContain('duration:')
                ->toContain('suites:')
                ->toContain("name: 'Basic Suite'");
        });

        test('calculates correct summary statistics for single suite', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suite = new TestSuite(
                name: 'Stats Suite',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::fail('t2', 'f1', 'g1', 'd2', [], true, false),
                ],
                duration: 2.5,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('total: 2')
                ->toContain('passed: 1')
                ->toContain('failed: 1')
                ->toContain('pass_rate: 50')
                ->toContain('duration: 2.5');
        });

        test('calculates correct summary statistics for multiple suites', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suites = [
                new TestSuite(
                    name: 'Suite 1',
                    results: [
                        TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                        TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
                    ],
                    duration: 1.0,
                ),
                new TestSuite(
                    name: 'Suite 2',
                    results: [
                        TestResult::pass('t3', 'f2', 'g2', 'd3', [], true),
                        TestResult::fail('t4', 'f2', 'g2', 'd4', [], true, false),
                    ],
                    duration: 1.5,
                ),
            ];

            // Act
            $renderer->render($suites);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('total: 4')
                ->toContain('passed: 3')
                ->toContain('failed: 1')
                ->toContain('pass_rate: 75')
                ->toContain('duration: 2.5');
        });

        test('handles empty suite array', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);

            // Act
            $renderer->render([]);

            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('total: 0')
                ->toContain('passed: 0')
                ->toContain('failed: 0')
                ->toContain('pass_rate: 0')
                ->toContain('duration: 0');
        });

        test('handles suite with zero tests', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suite = new TestSuite(
                name: 'Empty Suite',
                results: [],
                duration: 0.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('total: 0')
                ->toContain('passed: 0')
                ->toContain('failed: 0')
                ->toContain('pass_rate: 0')
                ->toContain("name: 'Empty Suite'");
        });

        test('rounds duration to 2 decimal places', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suite = new TestSuite(
                name: 'Precise Suite',
                results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                duration: 1.234_567_89,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)->toContain('duration: 1.23');
        });

        test('rounds pass rate to 1 decimal place', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suite = new TestSuite(
                name: 'Pass Rate Suite',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
                    TestResult::fail('t3', 'f1', 'g1', 'd3', [], true, false),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)->toMatch('/pass_rate: 66\.7/');
        });

        test('does not include failures when includeFailures is false', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output, includeFailures: false);
            $suite = new TestSuite(
                name: 'Suite With Failures',
                results: [
                    TestResult::fail('t1', 'file.json', 'group', 'test', ['data'], true, false, 'Error message'),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->not->toContain('failures:')
                ->not->toContain('Error message');
        });

        test('includes failures when includeFailures is true', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'Suite With Failures',
                results: [
                    TestResult::fail('test-1', 'validation.json', 'String validation', 'should reject empty', '', true, false, 'Validation error'),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('failures:')
                ->toContain('id: test-1')
                ->toContain('file: validation.json')
                ->toContain("group: 'String validation'")
                ->toContain("description: 'should reject empty'")
                ->toContain("error: 'Validation error'");
        });

        test('includes all failure details when includeFailures is true', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'Detailed Failures',
                results: [
                    TestResult::fail(
                        id: 'test-123',
                        file: 'schema.json',
                        group: 'Type validation',
                        description: 'should validate number type',
                        data: ['value' => 'not a number'],
                        expectedValid: true,
                        actualValid: false,
                        error: 'Type mismatch: expected number, got string',
                    ),
                ],
                duration: 0.5,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('expected_valid: true')
                ->toContain('actual_valid: false')
                ->toContain('data:')
                ->toContain("value: 'not a number'");
        });

        test('does not include failures section when suite has no failures', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output, includeFailures: true);
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
            $result = $output->fetch();

            // Assert
            expect($result)->not->toContain('failures:');
        });

        test('includes multiple failures in order', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'Multiple Failures',
                results: [
                    TestResult::fail('f1', 'file1.json', 'group1', 'first failure', [], true, false, 'Error 1'),
                    TestResult::fail('f2', 'file2.json', 'group2', 'second failure', [], true, false, 'Error 2'),
                    TestResult::pass('p1', 'file3.json', 'group3', 'passing test', [], true),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('id: f1')
                ->toContain('id: f2')
                ->toContain('Error 1')
                ->toContain('Error 2')
                ->not->toContain('id: p1');
        });

        test('produces valid YAML structure', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suite = new TestSuite(
                name: 'Valid YAML Suite',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toBeString()
                ->not->toBeEmpty();
        });
    });

    describe('formatSuite method (via render)', function (): void {
        test('formats suite with all required fields', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suite = new TestSuite(
                name: 'Complete Suite',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::fail('t2', 'f1', 'g1', 'd2', [], true, false),
                ],
                duration: 3.456,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain("name: 'Complete Suite'")
                ->toContain('total: 2')
                ->toContain('passed: 1')
                ->toContain('failed: 1')
                ->toContain('pass_rate: 50')
                ->toContain('duration: 3.46');
        });

        test('formats multiple suites correctly', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
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
            ];

            // Act
            $renderer->render($suites);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain("name: 'First Suite'")
                ->toContain("name: 'Second Suite'");
        });

        test('handles suite with complex data in failures', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output, includeFailures: true);
            $complexData = [
                'nested' => ['key' => 'value'],
                'array' => [1, 2, 3],
                'string' => 'test',
                'number' => 42,
                'boolean' => true,
            ];
            $suite = new TestSuite(
                name: 'Complex Data Suite',
                results: [
                    TestResult::fail('t1', 'f1', 'g1', 'd1', $complexData, true, false),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('data:')
                ->toContain('nested:')
                ->toContain('array:')
                ->toBeString();
        });

        test('handles null error in failure', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'Null Error Suite',
                results: [
                    TestResult::fail('t1', 'f1', 'g1', 'd1', [], true, false),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('failures:')
                ->toContain('error: null');
        });

        test('handles expected_valid false in failures', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'Expected False Suite',
                results: [
                    TestResult::fail('t1', 'f1', 'g1', 'd1', [], false, true, 'Should have failed'),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('expected_valid: false')
                ->toContain('actual_valid: true');
        });

        test('handles zero duration', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suite = new TestSuite(
                name: 'Zero Duration',
                results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                duration: 0.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)->toContain('duration: 0');
        });

        test('handles special characters in suite name', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suite = new TestSuite(
                name: 'Suite: With "Special" Characters & Symbols',
                results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)->toContain('Suite: With "Special" Characters & Symbols');
        });
    });

    describe('edge cases', function (): void {
        test('handles all tests failing', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'All Failed',
                results: [
                    TestResult::fail('t1', 'f1', 'g1', 'd1', [], true, false),
                    TestResult::fail('t2', 'f1', 'g1', 'd2', [], true, false),
                    TestResult::fail('t3', 'f1', 'g1', 'd3', [], true, false),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('total: 3')
                ->toContain('passed: 0')
                ->toContain('failed: 3')
                ->toContain('pass_rate: 0')
                ->toContain('failures:');
        });

        test('handles all tests passing', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'All Passed',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
                    TestResult::pass('t3', 'f1', 'g1', 'd3', [], true),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('total: 3')
                ->toContain('passed: 3')
                ->toContain('failed: 0')
                ->toContain('pass_rate: 100')
                ->not->toContain('failures:');
        });

        test('handles large number of tests', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $results = [];

            for ($i = 1; $i <= 100; ++$i) {
                $results[] = TestResult::pass('t' . $i, 'f1', 'g1', 'd' . $i, [], true);
            }

            $suite = new TestSuite(
                name: 'Large Suite',
                results: $results,
                duration: 10.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('total: 100')
                ->toContain('passed: 100')
                ->toContain('failed: 0')
                ->toContain('pass_rate: 100');
        });

        test('handles empty string values in failure data', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output, includeFailures: true);
            $suite = new TestSuite(
                name: 'Empty Values',
                results: [
                    TestResult::fail('', '', '', '', '', true, false, ''),
                ],
                duration: 1.0,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)
                ->toContain('failures:')
                ->toBeString();
        });

        test('handles very small duration values', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suite = new TestSuite(
                name: 'Fast Suite',
                results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                duration: 0.001,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)->toContain('duration: 0');
        });

        test('handles very large duration values', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $renderer = new YamlRenderer($output);
            $suite = new TestSuite(
                name: 'Slow Suite',
                results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                duration: 9_999.99,
            );

            // Act
            $renderer->render([$suite]);
            $result = $output->fetch();

            // Assert
            expect($result)->toContain('duration: 9999.99');
        });
    });
});
