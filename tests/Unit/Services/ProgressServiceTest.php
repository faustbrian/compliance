<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Services\ProgressService;
use Cline\Prism\ValueObjects\TestResult;
use Illuminate\Support\Sleep;
use Symfony\Component\Console\Output\BufferedOutput;

describe('ProgressService', function (): void {
    describe('constructor', function (): void {
        test('creates instance with default verbose false', function (): void {
            // Arrange
            $output = new BufferedOutput();

            // Act
            $service = new ProgressService($output);

            // Assert
            expect($service)->toBeInstanceOf(ProgressService::class);
        });

        test('creates instance with verbose enabled', function (): void {
            // Arrange
            $output = new BufferedOutput();

            // Act
            $service = new ProgressService($output, verbose: true);

            // Assert
            expect($service)->toBeInstanceOf(ProgressService::class);
        });
    });

    describe('start()', function (): void {
        test('initializes progress bar in non-verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);

            // Act
            $service->start(10);

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('0/10')
                ->and($display)->toContain('0%')
                ->and($display)->toContain('Starting...');
        });

        test('outputs message in verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);

            // Act
            $service->start(10);

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('Running 10 tests...');
        });

        test('resets counters on start', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);
            $passingResult = TestResult::pass(
                id: 'test:1',
                file: 'test.yml',
                group: 'Group',
                description: 'Test',
                data: [],
                expected: true,
            );

            // Act - First run
            $service->start(5);
            $service->advance($passingResult);
            $service->finish();

            // Clear output
            $output->fetch();

            // Act - Second run (counters should reset)
            $service->start(3);
            $display = $output->fetch();

            // Assert
            expect($display)->toContain('0/3');
        });

        test('handles zero tests in non-verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);

            // Act
            $service->start(0);

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('0/0');
        });

        test('handles zero tests in verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);

            // Act
            $service->start(0);

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('Running 0 tests...');
        });

        test('handles large number of tests', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);

            // Act
            $service->start(10_000);

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('0/10000');
        });
    });

    describe('advance()', function (): void {
        test('handles passing test in non-verbose mode without errors', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.yml',
                group: 'Group',
                description: 'Test description',
                data: ['key' => 'value'],
                expected: true,
            );

            // Act & Assert - Should not throw any errors
            $service->start(5);
            $service->advance($result);

            // BufferedOutput doesn't update progress bar like terminal, so just verify it started
            $display = $output->fetch();
            expect($display)->toContain('0/5');
        });

        test('handles failing test in non-verbose mode without errors', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);
            $result = TestResult::fail(
                id: 'test:1',
                file: 'test.yml',
                group: 'Group',
                description: 'Test description',
                data: ['key' => 'value'],
                expected: true,
                actual: false,
                error: 'Validation failed',
            );

            // Act & Assert - Should not throw any errors
            $service->start(5);
            $service->advance($result);

            // BufferedOutput doesn't update progress bar like terminal, so just verify it started
            $display = $output->fetch();
            expect($display)->toContain('0/5');
        });

        test('handles multiple advances with mixed results in non-verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);
            $passingResult = TestResult::pass(
                id: 'test:1',
                file: 'test.yml',
                group: 'Group',
                description: 'Passing test',
                data: [],
                expected: true,
            );
            $failingResult = TestResult::fail(
                id: 'test:2',
                file: 'test.yml',
                group: 'Group',
                description: 'Failing test',
                data: [],
                expected: true,
                actual: false,
            );

            // Act & Assert - Should not throw any errors
            $service->start(5);
            $service->advance($passingResult);
            $service->advance($passingResult);
            $service->advance($failingResult);

            // Verify progress bar was initialized
            $display = $output->fetch();
            expect($display)->toContain('0/5');
        });

        test('outputs verbose result for passing test', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.yml',
                group: 'UserValidation',
                description: 'validates user email format',
                data: ['email' => 'test@example.com'],
                expected: true,
                duration: 0.123,
            );

            // Act
            $service->start(5);
            $output->fetch();
            $service->advance($result);

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('✓')
                ->and($display)->toContain('UserValidation')
                ->and($display)->toContain('validates user email format')
                ->and($display)->toContain('0.123s');
        });

        test('outputs verbose result for failing test with error', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);
            $result = TestResult::fail(
                id: 'test:1',
                file: 'test.yml',
                group: 'UserValidation',
                description: 'rejects invalid email',
                data: ['email' => 'invalid'],
                expected: false,
                actual: true,
                error: 'Expected validation to fail but it passed',
                duration: 0.056,
            );

            // Act
            $service->start(5);
            $output->fetch();
            $service->advance($result);

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('✗')
                ->and($display)->toContain('UserValidation')
                ->and($display)->toContain('rejects invalid email')
                ->and($display)->toContain('0.056s')
                ->and($display)->toContain('Expected validation to fail but it passed');
        });

        test('outputs verbose result for failing test without error', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);
            $result = TestResult::fail(
                id: 'test:1',
                file: 'test.yml',
                group: 'UserValidation',
                description: 'test without error message',
                data: [],
                expected: true,
                actual: false,
            );

            // Act
            $service->start(5);
            $output->fetch();
            $service->advance($result);

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('✗')
                ->and($display)->toContain('UserValidation')
                ->and($display)->toContain('test without error message')
                ->and($display)->not->toContain('  <fg=red>'); // No error line should be displayed
        });

        test('outputs verbose result for passing test with null error', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.yml',
                group: 'Group',
                description: 'passing test',
                data: [],
                expected: true,
            );

            // Act
            $service->start(5);
            $output->fetch();
            $service->advance($result);

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('✓')
                ->and($display)->not->toContain('  <fg=red>'); // No error line
        });

        test('handles advance without prior start in non-verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.yml',
                group: 'Group',
                description: 'Test',
                data: [],
                expected: true,
            );

            // Act
            $service->advance($result);

            // Assert
            $display = $output->fetch();
            expect($display)->toBe(''); // No output because progressBar is null
        });

        test('handles multiple passing tests without errors', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.yml',
                group: 'Group',
                description: 'Test',
                data: [],
                expected: true,
            );

            // Act & Assert - Should not throw any errors
            $service->start(10);

            foreach (range(1, 5) as $i) {
                $service->advance($result);
            }

            // Verify progress bar was initialized
            $display = $output->fetch();
            expect($display)->toContain('0/10');
        });

        test('handles multiple failing tests without errors', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);
            $result = TestResult::fail(
                id: 'test:1',
                file: 'test.yml',
                group: 'Group',
                description: 'Test',
                data: [],
                expected: true,
                actual: false,
            );

            // Act & Assert - Should not throw any errors
            $service->start(10);

            foreach (range(1, 3) as $i) {
                $service->advance($result);
            }

            // Verify progress bar was initialized
            $display = $output->fetch();
            expect($display)->toContain('0/10');
        });

        test('handles very short duration in verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.yml',
                group: 'Group',
                description: 'Fast test',
                data: [],
                expected: true,
                duration: 0.001,
            );

            // Act
            $service->start(5);
            $output->fetch();
            $service->advance($result);

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('0.001s');
        });

        test('handles very long duration in verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.yml',
                group: 'Group',
                description: 'Slow test',
                data: [],
                expected: true,
                duration: 123.456,
            );

            // Act
            $service->start(5);
            $output->fetch();
            $service->advance($result);

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('123.456s');
        });
    });

    describe('finish()', function (): void {
        test('completes progress bar in non-verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);

            // Act
            $service->start(5);
            $service->finish();

            $display = $output->fetch();

            // Assert
            expect($display)->toContain('5/5')
                ->and($display)->toContain('100%');
        });

        test('outputs summary in verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);
            $passingResult = TestResult::pass(
                id: 'test:1',
                file: 'test.yml',
                group: 'Group',
                description: 'Test',
                data: [],
                expected: true,
            );
            $failingResult = TestResult::fail(
                id: 'test:2',
                file: 'test.yml',
                group: 'Group',
                description: 'Test',
                data: [],
                expected: true,
                actual: false,
            );

            // Act
            $service->start(3);
            $output->fetch();
            $service->advance($passingResult);
            $service->advance($passingResult);
            $service->advance($failingResult);

            $output->fetch();
            $service->finish();

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('Completed in')
                ->and($display)->toContain('2 passed, 1 failed');
        });

        test('displays duration in verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);

            // Act
            $service->start(5);

            $output->fetch();
            Sleep::usleep(100_000); // Sleep for 0.1 seconds
            $service->finish();

            // Assert
            $display = $output->fetch();
            expect($display)->toMatch('/Completed in \d+\.\d{2}s/');
        });

        test('handles finish without prior start in non-verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);

            // Act
            $service->finish();

            // Assert
            $display = $output->fetch();
            expect($display)->toBe(''); // No output because progressBar is null
        });

        test('outputs exact format in verbose mode with zero tests', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);

            // Act
            $service->start(0);

            $output->fetch();
            $service->finish();

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('0 passed, 0 failed');
        });

        test('handles multiple finish calls in non-verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);

            // Act
            $service->start(5);
            $service->finish();

            $output->fetch(); // Clear first finish
            $service->finish(); // Second finish call
            $display = $output->fetch();

            // Assert
            expect($display)->toContain("\n\n"); // Still outputs blank lines
        });

        test('adds blank lines after completion in non-verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);

            // Act
            $service->start(5);

            $output->fetch();
            $service->finish();

            // Assert
            $display = $output->fetch();
            $lines = explode("\n", $display);
            $emptyLineCount = count(array_filter($lines, fn ($line): bool => mb_trim($line) === ''));
            expect($emptyLineCount)->toBeGreaterThanOrEqual(2);
        });

        test('adds blank lines in verbose mode summary', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);

            // Act
            $service->start(5);

            $output->fetch();
            $service->finish();

            // Assert
            $display = $output->fetch();
            expect($display)->toContain("\n\n");
        });
    });

    describe('integration scenarios', function (): void {
        test('completes full test run in non-verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);
            $results = [
                TestResult::pass('test:1', 'test.yml', 'Group', 'Test 1', [], true),
                TestResult::pass('test:2', 'test.yml', 'Group', 'Test 2', [], true),
                TestResult::fail('test:3', 'test.yml', 'Group', 'Test 3', [], true, false, 'Error'),
                TestResult::pass('test:4', 'test.yml', 'Group', 'Test 4', [], true),
                TestResult::fail('test:5', 'test.yml', 'Group', 'Test 5', [], true, false, 'Error'),
            ];

            // Act
            $service->start(5);

            foreach ($results as $result) {
                $service->advance($result);
            }

            $service->finish();
            $display = $output->fetch();

            // Assert
            expect($display)->toContain('5/5')
                ->and($display)->toContain('100%');
        });

        test('completes full test run in verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);
            $results = [
                TestResult::pass('test:1', 'test.yml', 'Group A', 'Test 1', [], true, 0.1),
                TestResult::fail('test:2', 'test.yml', 'Group B', 'Test 2', [], true, false, 'Error message', 0.2),
                TestResult::pass('test:3', 'test.yml', 'Group A', 'Test 3', [], true, 0.15),
            ];

            // Act
            $service->start(3);

            foreach ($results as $result) {
                $service->advance($result);
            }

            $service->finish();

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('Running 3 tests...')
                ->and($display)->toContain('✓')
                ->and($display)->toContain('✗')
                ->and($display)->toContain('Group A')
                ->and($display)->toContain('Group B')
                ->and($display)->toContain('Error message')
                ->and($display)->toContain('Completed in')
                ->and($display)->toContain('2 passed, 1 failed');
        });

        test('handles empty test suite in non-verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);

            // Act
            $service->start(0);
            $service->finish();

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('0/0');
        });

        test('handles empty test suite in verbose mode', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: true);

            // Act
            $service->start(0);

            $output->fetch();
            $service->finish();

            // Assert
            $display = $output->fetch();
            expect($display)->toContain('0 passed, 0 failed');
        });

        test('handles all passing tests', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);
            $result = TestResult::pass('test:1', 'test.yml', 'Group', 'Test', [], true);

            // Act
            $service->start(10);

            foreach (range(1, 10) as $i) {
                $service->advance($result);
            }

            $display = $output->fetch();

            // Assert
            expect($display)->toContain('10/10');
        });

        test('handles all failing tests', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);
            $result = TestResult::fail('test:1', 'test.yml', 'Group', 'Test', [], true, false);

            // Act
            $service->start(10);

            foreach (range(1, 10) as $i) {
                $service->advance($result);
            }

            $display = $output->fetch();

            // Assert
            expect($display)->toContain('10/10');
        });

        test('handles rapid test execution', function (): void {
            // Arrange
            $output = new BufferedOutput();
            $service = new ProgressService($output, verbose: false);
            $passingResult = TestResult::pass('test:1', 'test.yml', 'Group', 'Test', [], true);
            $failingResult = TestResult::fail('test:2', 'test.yml', 'Group', 'Test', [], true, false);

            // Act
            $service->start(100);

            for ($i = 0; $i < 100; ++$i) {
                $result = $i % 3 === 0 ? $failingResult : $passingResult;
                $service->advance($result);
            }

            $service->finish();
            $display = $output->fetch();

            // Assert
            expect($display)->toContain('100/100');
        });
    });
});
