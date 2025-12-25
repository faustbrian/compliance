<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Compliance\Output\CiRenderer;
use Cline\Compliance\ValueObjects\TestResult;
use Cline\Compliance\ValueObjects\TestSuite;
use Symfony\Component\Console\Output\BufferedOutput;

describe('CiRenderer', function (): void {
    describe('render method', function (): void {
        test('renders suite summary', function (): void {
            $output = new BufferedOutput();
            $renderer = new CiRenderer($output);

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
            $renderer = new CiRenderer($output);

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
            $renderer = new CiRenderer($output);

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
            $renderer = new CiRenderer($output);

            $renderer->render([]);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('Compliance Test Suite')
                ->toContain('Total:')
                ->toContain('0 tests')
                ->toContain('Passed:')
                ->toContain('Failed:')
                ->toContain('Duration:')
                ->toContain('0.0%');
        });

        test('renders suite with all failed tests', function (): void {
            $output = new BufferedOutput();
            $renderer = new CiRenderer($output);

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
            $renderer = new CiRenderer($output);

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
            $renderer = new CiRenderer($output);

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
            $renderer = new CiRenderer($output);

            $suite = new TestSuite(
                name: 'Error Suite',
                results: [
                    TestResult::fail(
                        id: 't1',
                        file: 'tests/example.php',
                        group: 'Validation',
                        description: 'Should validate input',
                        data: ['key' => 'value'],
                        expectedValid: true,
                        actualValid: false,
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
                ->toContain('tests/example.php')
                ->toContain('Group: Validation')
                ->toContain('Test: Should validate input');
        });

        test('renders failure without error message', function (): void {
            $output = new BufferedOutput();
            $renderer = new CiRenderer($output);

            $suite = new TestSuite(
                name: 'No Error Suite',
                results: [
                    TestResult::fail(
                        id: 't1',
                        file: 'tests/example.php',
                        group: 'Validation',
                        description: 'Should fail validation',
                        data: ['key' => 'value'],
                        expectedValid: true,
                        actualValid: false,
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
            $renderer = new CiRenderer($output);

            $suite = new TestSuite(
                name: 'Mixed Errors',
                results: [
                    TestResult::fail(
                        id: 't1',
                        file: 'tests/file1.php',
                        group: 'Group 1',
                        description: 'Test with error',
                        data: ['data' => 'value1'],
                        expectedValid: true,
                        actualValid: false,
                        error: 'First error message',
                    ),
                    TestResult::fail(
                        id: 't2',
                        file: 'tests/file2.php',
                        group: 'Group 2',
                        description: 'Test without error',
                        data: ['data' => 'value2'],
                        expectedValid: true,
                        actualValid: false,
                    ),
                    TestResult::fail(
                        id: 't3',
                        file: 'tests/file3.php',
                        group: 'Group 3',
                        description: 'Test with another error',
                        data: ['data' => 'value3'],
                        expectedValid: false,
                        actualValid: true,
                        error: 'Second error message',
                    ),
                ],
                duration: 1.5,
            );

            $renderer->renderFailures($suite);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('1. tests/file1.php')
                ->toContain('2. tests/file2.php')
                ->toContain('3. tests/file3.php')
                ->toContain('Error: First error message')
                ->toContain('Error: Second error message')
                ->toContain('Group: Group 1')
                ->toContain('Group: Group 2')
                ->toContain('Group: Group 3');
        });

        test('renders complex test data in JSON format', function (): void {
            $output = new BufferedOutput();
            $renderer = new CiRenderer($output);

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
                        expectedValid: true,
                        actualValid: false,
                        error: 'Complex validation failed',
                    ),
                ],
                duration: 1.0,
            );

            $renderer->renderFailures($suite);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('Data: '.json_encode($complexData));
        });

        test('renders expected vs actual validation states correctly', function (): void {
            $output = new BufferedOutput();
            $renderer = new CiRenderer($output);

            $suite = new TestSuite(
                name: 'Validation States',
                results: [
                    TestResult::fail(
                        id: 't1',
                        file: 'tests/valid_to_invalid.php',
                        group: 'State Test',
                        description: 'Expected valid, got invalid',
                        data: [],
                        expectedValid: true,
                        actualValid: false,
                    ),
                    TestResult::fail(
                        id: 't2',
                        file: 'tests/invalid_to_valid.php',
                        group: 'State Test',
                        description: 'Expected invalid, got valid',
                        data: [],
                        expectedValid: false,
                        actualValid: true,
                    ),
                ],
                duration: 1.0,
            );

            $renderer->renderFailures($suite);

            $outputContent = $output->fetch();

            expect($outputContent)
                ->toContain('Expected: VALID, Got: INVALID')
                ->toContain('Expected: INVALID, Got: VALID');
        });
    });
});
