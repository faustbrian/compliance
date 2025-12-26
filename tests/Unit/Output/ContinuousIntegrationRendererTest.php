<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Output\ContinuousIntegrationRenderer;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;
use Symfony\Component\Console\Output\BufferedOutput;

describe('ContinuousIntegrationRenderer', function (): void {
    describe('render method', function (): void {
        test('renders suite summary', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $suite = new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                    TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
                ],
                duration: 1.5,
            );

            $renderer->render([$suite]);

            expect($output->fetch())->toContain('Test Suite');
        });

        test('renders all passing message', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $suite = new TestSuite(
                name: 'Perfect',
                results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                duration: 1.0,
            );

            $renderer->render([$suite]);

            expect($output->fetch())
                ->toContain('Perfect')
                ->toContain('1 test');
        });

        test('renders multiple suites with mixed results', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

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

            $renderer->render($suites);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('Suite 1:')
                ->toContain('Suite 2:')
                ->toContain('✓')
                ->toContain('✗')
                ->toContain('Total:')
                ->toContain('Passed:')
                ->toContain('Failed:')
                ->toContain('Duration:');
        });

        test('renders empty suite array', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $renderer->render([]);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('Prism Test Suite')
                ->toContain('Total:')
                ->toContain('0 tests')
                ->toContain('Passed:')
                ->toContain('Failed:')
                ->toContain('Duration:')
                ->toContain('0.0%');
        });

        test('renders suite with all failed tests', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $suite = new TestSuite(
                name: 'All Failed',
                results: [
                    TestResult::fail('t1', 'f1', 'g1', 'd1', [], true, false),
                    TestResult::fail('t2', 'f1', 'g1', 'd2', [], true, false),
                    TestResult::fail('t3', 'f1', 'g1', 'd3', [], true, false),
                ],
                duration: 1.0,
            );

            $renderer->render([$suite]);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('✗ All Failed:')
                ->toContain('0/   3 tests')
                ->toContain('Failed:')
                ->toContain('3 tests')
                ->toContain('100.0%');
        });
    });

    describe('renderFailures method', function (): void {
        test('renders failures when present', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $suite = new TestSuite(
                name: 'Failed Suite',
                results: [TestResult::fail('t1', 'f1', 'g1', 'd1', [], true, false)],
                duration: 1.0,
            );

            $renderer->render([$suite]);
            $renderer->renderFailures($suite);

            expect($output->fetch())->toContain('Failures for');
        });

        test('renders nothing when no failures', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $suite = new TestSuite(
                name: 'Success',
                results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                duration: 1.0,
            );

            $before = $output->fetch();
            $renderer->renderFailures($suite);
            $after = $output->fetch();

            expect($after)->toBe($before);
        });

        test('renders failure with error message', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $suite = new TestSuite(
                name: 'Error Suite',
                results: [
                    TestResult::fail(
                        id: 't1',
                        file: 'tests/example.php',
                        group: 'Validation',
                        description: 'Should validate input',
                        data: ['key' => 'value'],
                        expected: true,
                        actual: false,
                        error: 'Validation failed: Invalid input format',
                    ),
                ],
                duration: 1.0,
            );

            $renderer->render([$suite]);
            $renderer->renderFailures($suite);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('Failures for Error Suite')
                ->toContain('Error: Validation failed: Invalid input format')
                ->toContain('File: tests/example.php')
                ->toContain('Group: Validation')
                ->toContain('1. Should validate input');
        });

        test('renders failure without error message', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $suite = new TestSuite(
                name: 'No Error Suite',
                results: [
                    TestResult::fail(
                        id: 't1',
                        file: 'tests/example.php',
                        group: 'Validation',
                        description: 'Should fail validation',
                        data: ['key' => 'value'],
                        expected: true,
                        actual: false,
                    ),
                ],
                duration: 1.0,
            );

            $renderer->renderFailures($suite);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('Failures for No Error Suite')
                ->not->toContain('Error:');
        });

        test('renders multiple failures with different error states', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $suite = new TestSuite(
                name: 'Mixed Errors',
                results: [
                    TestResult::fail(
                        id: 't1',
                        file: 'tests/file1.php',
                        group: 'Group 1',
                        description: 'Test with error',
                        data: ['data' => 'value1'],
                        expected: true,
                        actual: false,
                        error: 'First error message',
                    ),
                    TestResult::fail(
                        id: 't2',
                        file: 'tests/file2.php',
                        group: 'Group 2',
                        description: 'Test without error',
                        data: ['data' => 'value2'],
                        expected: true,
                        actual: false,
                    ),
                    TestResult::fail(
                        id: 't3',
                        file: 'tests/file3.php',
                        group: 'Group 3',
                        description: 'Test with another error',
                        data: ['data' => 'value3'],
                        expected: false,
                        actual: true,
                        error: 'Second error message',
                    ),
                ],
                duration: 1.5,
            );

            $renderer->renderFailures($suite);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('1. Test with error')
                ->toContain('2. Test without error')
                ->toContain('3. Test with another error')
                ->toContain('File: tests/file1.php')
                ->toContain('File: tests/file2.php')
                ->toContain('File: tests/file3.php')
                ->toContain('Error: First error message')
                ->toContain('Error: Second error message')
                ->toContain('Group: Group 1')
                ->toContain('Group: Group 2')
                ->toContain('Group: Group 3');
        });

        test('renders complex test data in JSON format', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $complexData = [
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
                'nested' => [
                    'level1' => ['level2' => 'value'],
                ],
            ];

            $suite = new TestSuite(
                name: 'Complex Data',
                results: [
                    TestResult::fail(
                        id: 't1',
                        file: 'tests/complex.php',
                        group: 'Complex',
                        description: 'Complex data test',
                        data: $complexData,
                        expected: true,
                        actual: false,
                        error: 'Complex validation failed',
                    ),
                ],
                duration: 1.0,
            );

            $renderer->renderFailures($suite);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('Test Data: '.json_encode($complexData, \JSON_PRETTY_PRINT));
        });

        test('renders expected vs actual validation states correctly', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $suite = new TestSuite(
                name: 'Validation States',
                results: [
                    TestResult::fail(
                        id: 't1',
                        file: 'tests/valid_to_invalid.php',
                        group: 'State Test',
                        description: 'Expected valid, got invalid',
                        data: [],
                        expected: true,
                        actual: false,
                    ),
                    TestResult::fail(
                        id: 't2',
                        file: 'tests/invalid_to_valid.php',
                        group: 'State Test',
                        description: 'Expected invalid, got valid',
                        data: [],
                        expected: false,
                        actual: true,
                    ),
                ],
                duration: 1.0,
            );

            $renderer->renderFailures($suite);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('Expected Validation: VALID')
                ->toContain('Actual Validation: INVALID')
                ->toContain('Expected Validation: INVALID')
                ->toContain('Actual Validation: VALID');
        });

        test('renders failure duration when duration is greater than zero (line 181 coverage)', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $suite = new TestSuite(
                name: 'Duration Suite',
                results: [
                    TestResult::fail(
                        id: 't1',
                        file: 'tests/duration_test.php',
                        group: 'Duration Test',
                        description: 'Test with positive duration',
                        data: ['key' => 'value'],
                        expected: true,
                        actual: false,
                        error: 'Test failed with measurable duration',
                        duration: 0.123_456,  // Positive duration to trigger line 181
                    ),
                ],
                duration: 0.123_456,
            );

            $renderer->renderFailures($suite);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('Duration: 123.46ms')  // 0.123456 * 1000 = 123.456ms, formatted as 123.46ms
                ->toContain('Test failed with measurable duration');
        });

        test('does not render failure duration when duration is zero', function (): void {
            $output = new BufferedOutput();
            $renderer = new ContinuousIntegrationRenderer($output);

            $suite = new TestSuite(
                name: 'Zero Duration Suite',
                results: [
                    TestResult::fail(
                        id: 't1',
                        file: 'tests/zero_duration.php',
                        group: 'Zero Duration Test',
                        description: 'Test with zero duration',
                        data: ['key' => 'value'],
                        expected: true,
                        actual: false,
                        error: 'Test failed instantly',
                        duration: 0.0,  // Zero duration - line 181 should not execute
                    ),
                ],
                duration: 0.0,
            );

            $renderer->renderFailures($suite);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->not->toContain('Duration:')
                ->toContain('Test failed instantly');
        });
    });
});
