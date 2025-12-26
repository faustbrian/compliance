<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Output\BenchmarkRenderer;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;

describe('BenchmarkRenderer', function (): void {
    describe('render method', function (): void {
        test('renders benchmark comparison with performance improvement', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite 1',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.5,
                ),
            ];
            $baselineData = [
                'Test Suite 1' => ['total_duration' => 2.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Benchmark Comparison')
                ->and($output)->toContain('Test Suite 1:')
                ->and($output)->toContain('Current: 1.500s | Baseline: 2.000s')
                ->and($output)->toContain('-0.500s')
                ->and($output)->toContain('-25.0%')
                ->and($output)->toContain('✓ Performance improved!');
        });

        test('renders benchmark comparison with performance regression', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite 1',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 2.5,
                ),
            ];
            $baselineData = [
                'Test Suite 1' => ['total_duration' => 2.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Benchmark Comparison')
                ->and($output)->toContain('Test Suite 1:')
                ->and($output)->toContain('Current: 2.500s | Baseline: 2.000s')
                ->and($output)->toContain('+0.500s')
                ->and($output)->toContain('+25.0%')
                ->and($output)->toContain('⚠ Performance regressed!');
        });

        test('renders benchmark comparison with no performance change', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite 1',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 2.0,
                ),
            ];
            $baselineData = [
                'Test Suite 1' => ['total_duration' => 2.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Benchmark Comparison')
                ->and($output)->toContain('Test Suite 1:')
                ->and($output)->toContain('Current: 2.000s | Baseline: 2.000s')
                ->and($output)->toContain('0.000s')
                ->and($output)->toContain('0.0%')
                ->and($output)->toContain('- No performance change');
        });

        test('handles multiple test suites with mixed results', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Fast Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
                new TestSuite(
                    name: 'Slow Suite',
                    results: [TestResult::pass('t2', 'f2', 'g2', 'd2', [], true)],
                    duration: 3.0,
                ),
            ];
            $baselineData = [
                'Fast Suite' => ['total_duration' => 1.5],
                'Slow Suite' => ['total_duration' => 2.5],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Fast Suite:')
                ->and($output)->toContain('Current: 1.000s | Baseline: 1.500s')
                ->and($output)->toContain('-0.500s')
                ->and($output)->toContain('Slow Suite:')
                ->and($output)->toContain('Current: 3.000s | Baseline: 2.500s')
                ->and($output)->toContain('+0.500s')
                ->and($output)->toContain('Overall:')
                ->and($output)->toContain('Current: 4.000s | Baseline: 4.000s');
        });

        test('skips suites not present in baseline data', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'New Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
                new TestSuite(
                    name: 'Existing Suite',
                    results: [TestResult::pass('t2', 'f2', 'g2', 'd2', [], true)],
                    duration: 2.0,
                ),
            ];
            $baselineData = [
                'Existing Suite' => ['total_duration' => 2.5],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->not->toContain('New Suite:')
                ->and($output)->toContain('Existing Suite:')
                ->and($output)->toContain('Current: 3.000s | Baseline: 2.500s');
        });

        test('handles baseline data with non-array suite entries', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 2.0],
                'Invalid Suite' => 'not-an-array',
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Test Suite:')
                ->and($output)->toContain('Current: 1.000s | Baseline: 2.000s')
                ->and($output)->toContain('Overall:');
        });

        test('handles baseline data with missing total_duration key', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['other_key' => 123],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Benchmark Comparison')
                ->and($output)->not->toContain('Test Suite:')
                ->and($output)->toContain('Overall:')
                ->and($output)->toContain('Current: 1.000s | Baseline: 0.000s');
        });

        test('handles baseline data with invalid total_duration type (string)', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 'invalid'],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Benchmark Comparison')
                ->and($output)->not->toContain('Test Suite:')
                ->and($output)->toContain('Overall:')
                ->and($output)->toContain('Current: 1.000s | Baseline: 0.000s');
        });

        test('handles baseline data with invalid total_duration type (null)', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => null],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Benchmark Comparison')
                ->and($output)->not->toContain('Test Suite:')
                ->and($output)->toContain('Overall:')
                ->and($output)->toContain('Current: 1.000s | Baseline: 0.000s');
        });

        test('handles baseline data with integer total_duration', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.5,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 2],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Test Suite:')
                ->and($output)->toContain('Current: 1.500s | Baseline: 2.000s')
                ->and($output)->toContain('-0.500s')
                ->and($output)->toContain('-25.0%');
        });

        test('handles empty suites array', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [];
            $baselineData = [
                'Test Suite' => ['total_duration' => 2.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Benchmark Comparison')
                ->and($output)->toContain('Overall:')
                ->and($output)->toContain('Current: 0.000s | Baseline: 2.000s')
                ->and($output)->toContain('✓ Performance improved!');
        });

        test('handles empty baseline data', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Benchmark Comparison')
                ->and($output)->not->toContain('Test Suite:')
                ->and($output)->toContain('Overall:')
                ->and($output)->toContain('Current: 1.000s | Baseline: 0.000s')
                ->and($output)->toContain('⚠ Performance regressed!');
        });

        test('calculates percentage change correctly with zero baseline', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 0.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Test Suite:')
                ->and($output)->toContain('Current: 1.000s | Baseline: 0.000s')
                ->and($output)->toContain('+1.000s')
                ->and($output)->toContain('0.0%');
        });

        test('formats floating point numbers with three decimal places', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.234_567,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 2.345_678],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Current: 1.235s | Baseline: 2.346s')
                ->and($output)->toContain('-1.111s');
        });

        test('formats percentage with one decimal place', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 3.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            // (1.0 - 3.0) / 3.0 * 100 = -66.666... should be formatted as -66.7%
            expect($output)->toContain('-66.7%');
        });

        test('uses green color for performance improvement', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 2.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('<fg=green>-')
                ->and($output)->toContain('<fg=green;options=bold>✓ Performance improved!</>');
        });

        test('uses red color for performance regression', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 2.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 1.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('<fg=red>+')
                ->and($output)->toContain('<fg=red;options=bold>⚠ Performance regressed!</>');
        });

        test('uses white color for no performance change', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 1.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('<fg=white>0.000s')
                ->and($output)->toContain('<fg=white;options=bold>- No performance change</>')
                ->and($output)->not->toContain('<fg=green>')
                ->and($output)->not->toContain('<fg=red>');
        });

        test('includes plus sign for positive differences', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 2.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 1.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('+1.000s')
                ->and($output)->toContain('+100.0%');
        });

        test('does not include plus sign for negative differences', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 2.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('-1.000s')
                ->and($output)->toContain('-50.0%')
                ->and($output)->not->toContain('+-');
        });

        test('handles multiple suites with cumulative totals', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Suite A',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.5,
                ),
                new TestSuite(
                    name: 'Suite B',
                    results: [TestResult::pass('t2', 'f2', 'g2', 'd2', [], true)],
                    duration: 2.5,
                ),
                new TestSuite(
                    name: 'Suite C',
                    results: [TestResult::pass('t3', 'f3', 'g3', 'd3', [], true)],
                    duration: 3.0,
                ),
            ];
            $baselineData = [
                'Suite A' => ['total_duration' => 1.0],
                'Suite B' => ['total_duration' => 2.0],
                'Suite C' => ['total_duration' => 4.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            // Total current: 1.5 + 2.5 + 3.0 = 7.0
            // Total baseline: 1.0 + 2.0 + 4.0 = 7.0
            expect($output)->toContain('Current: 7.000s | Baseline: 7.000s')
                ->and($output)->toContain('0.000s (0.0%)')
                ->and($output)->toContain('- No performance change');
        });

        test('handles baseline data with both integer and float durations', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Suite A',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.5,
                ),
                new TestSuite(
                    name: 'Suite B',
                    results: [TestResult::pass('t2', 'f2', 'g2', 'd2', [], true)],
                    duration: 2.5,
                ),
            ];
            $baselineData = [
                'Suite A' => ['total_duration' => 1],
                'Suite B' => ['total_duration' => 2.5],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Suite A:')
                ->and($output)->toContain('Current: 1.500s | Baseline: 1.000s')
                ->and($output)->toContain('Suite B:')
                ->and($output)->toContain('Current: 2.500s | Baseline: 2.500s');
        });

        test('handles very small duration differences', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.000_1,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 1.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('Current: 1.000s | Baseline: 1.000s')
                ->and($output)->toContain('+0.000s')
                ->and($output)->toContain('+0.0%');
        });

        test('handles large percentage changes', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 10.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 1.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            // (10.0 - 1.0) / 1.0 * 100 = 900.0%
            expect($output)->toContain('+9.000s')
                ->and($output)->toContain('+900.0%');
        });

        test('includes newline at start and end of output', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Test Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [
                'Test Suite' => ['total_duration' => 1.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toStartWith("\n")
                ->and($output)->toEndWith("\n");
        });

        test('formats suite names correctly', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Integration Tests Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
            ];
            $baselineData = [
                'Integration Tests Suite' => ['total_duration' => 1.5],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            expect($output)->toContain('<fg=white>Integration Tests Suite:</>');
        });

        test('handles mixed baseline data quality across suites', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Valid Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
                new TestSuite(
                    name: 'Invalid Suite',
                    results: [TestResult::pass('t2', 'f2', 'g2', 'd2', [], true)],
                    duration: 2.0,
                ),
                new TestSuite(
                    name: 'Missing Suite',
                    results: [TestResult::pass('t3', 'f3', 'g3', 'd3', [], true)],
                    duration: 3.0,
                ),
            ];
            $baselineData = [
                'Valid Suite' => ['total_duration' => 1.5],
                'Invalid Suite' => ['total_duration' => 'not-a-number'],
                'Other Suite' => ['total_duration' => 2.0],
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            // Total baseline includes Valid Suite (1.5) + Other Suite (2.0) = 3.5
            // Invalid Suite is skipped due to invalid type
            expect($output)->toContain('Valid Suite:')
                ->and($output)->toContain('Current: 1.000s | Baseline: 1.500s')
                ->and($output)->not->toContain('Invalid Suite:')
                ->and($output)->not->toContain('Missing Suite:')
                ->and($output)->toContain('Overall:')
                ->and($output)->toContain('Current: 6.000s | Baseline: 3.500s');
        });

        test('handles suite baseline data that is not an array (line 79 coverage)', function (): void {
            // Arrange
            $renderer = new BenchmarkRenderer();
            $suites = [
                new TestSuite(
                    name: 'Valid Suite',
                    results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                    duration: 1.0,
                ),
                new TestSuite(
                    name: 'Non-Array Baseline Suite',
                    results: [TestResult::pass('t2', 'f2', 'g2', 'd2', [], true)],
                    duration: 2.0,
                ),
            ];
            $baselineData = [
                'Valid Suite' => ['total_duration' => 1.5],
                'Non-Array Baseline Suite' => 'not-an-array',  // This will trigger line 79
            ];

            // Act
            $output = $renderer->render($suites, $baselineData);

            // Assert
            // Valid Suite should be rendered, Non-Array Baseline Suite should be skipped
            expect($output)->toContain('Valid Suite:')
                ->and($output)->toContain('Current: 1.000s | Baseline: 1.500s')
                ->and($output)->not->toContain('Non-Array Baseline Suite:')
                ->and($output)->toContain('Overall:')
                ->and($output)->toContain('Current: 3.000s | Baseline: 1.500s');
        });
    });
});
