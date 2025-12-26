<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Output\FuzzingRenderer;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;

describe('FuzzingRenderer', function (): void {
    beforeEach(function (): void {
        $this->renderer = new FuzzingRenderer();
    });

    describe('render()', function (): void {
        describe('all tests passed', function (): void {
            test('renders success message when all tests pass', function (): void {
                // Arrange
                $results = [
                    TestResult::pass(
                        id: 'fuzzing:test1:0:0',
                        file: 'fuzz/test1.json',
                        group: 'String Validation',
                        description: 'valid string input',
                        data: 'hello',
                        expected: true,
                        duration: 0.001_2,
                    ),
                    TestResult::pass(
                        id: 'fuzzing:test1:0:1',
                        file: 'fuzz/test1.json',
                        group: 'String Validation',
                        description: 'invalid number input',
                        data: 42,
                        expected: false,
                        duration: 0.001_5,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'Fuzzing Suite',
                    results: $results,
                    duration: 0.002_7,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Fuzzing Test Results')
                    ->and($output)->toContain('Total Fuzzed Tests: 2')
                    ->and($output)->toContain('Passed: <fg=green>2</>')
                    ->and($output)->toContain('Failed: <fg=red>0</>')
                    ->and($output)->toContain('✓ All fuzzed tests passed!')
                    ->and($output)->toContain('No unexpected errors or validation failures discovered.')
                    ->and($output)->not->toContain('Pass Rate')
                    ->and($output)->not->toContain('Found');
            });

            test('handles empty test suite', function (): void {
                // Arrange
                $suite = new TestSuite(
                    name: 'Empty Fuzzing Suite',
                    results: [],
                    duration: 0.0,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Total Fuzzed Tests: 0')
                    ->and($output)->toContain('Passed: <fg=green>0</>')
                    ->and($output)->toContain('Failed: <fg=red>0</>')
                    ->and($output)->toContain('✓ All fuzzed tests passed!');
            });

            test('handles single passing test', function (): void {
                // Arrange
                $results = [
                    TestResult::pass(
                        id: 'fuzzing:single:0:0',
                        file: 'fuzz/single.json',
                        group: 'Edge Cases',
                        description: 'empty string',
                        data: '',
                        expected: false,
                        duration: 0.000_1,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'Single Test',
                    results: $results,
                    duration: 0.000_1,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Total Fuzzed Tests: 1')
                    ->and($output)->toContain('Passed: <fg=green>1</>')
                    ->and($output)->toContain('Failed: <fg=red>0</>')
                    ->and($output)->toContain('✓ All fuzzed tests passed!');
            });

            test('handles large number of passing tests', function (): void {
                // Arrange
                $results = [];

                for ($i = 0; $i < 1_000; ++$i) {
                    $results[] = TestResult::pass(
                        id: 'fuzzing:large:0:'.$i,
                        file: 'fuzz/large.json',
                        group: 'Large Test Group',
                        description: 'test case '.$i,
                        data: $i,
                        expected: true,
                        duration: 0.000_1,
                    );
                }

                $suite = new TestSuite(
                    name: 'Large Suite',
                    results: $results,
                    duration: 0.1,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Total Fuzzed Tests: 1000')
                    ->and($output)->toContain('Passed: <fg=green>1000</>')
                    ->and($output)->toContain('Failed: <fg=red>0</>')
                    ->and($output)->toContain('✓ All fuzzed tests passed!');
            });
        });

        describe('some tests failed', function (): void {
            test('renders failure details when tests fail with errors', function (): void {
                // Arrange
                $results = [
                    TestResult::pass(
                        id: 'fuzzing:test2:0:0',
                        file: 'fuzz/test2.json',
                        group: 'Number Validation',
                        description: 'valid number',
                        data: 42,
                        expected: true,
                        duration: 0.001_0,
                    ),
                    TestResult::fail(
                        id: 'fuzzing:test2:0:1',
                        file: 'fuzz/test2.json',
                        group: 'Number Validation',
                        description: 'invalid string as number',
                        data: 'not-a-number',
                        expected: false,
                        actual: true,
                        error: 'Validation mismatch: expected false, got true',
                        duration: 0.001_5,
                    ),
                    TestResult::fail(
                        id: 'fuzzing:test2:1:0',
                        file: 'fuzz/test2.json',
                        group: 'Boundary Tests',
                        description: 'maximum integer',
                        data: \PHP_INT_MAX,
                        expected: true,
                        actual: false,
                        error: 'Integer overflow detected',
                        duration: 0.002_0,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'Mixed Results Suite',
                    results: $results,
                    duration: 0.004_5,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Fuzzing Test Results')
                    ->and($output)->toContain('Total Fuzzed Tests: 3')
                    ->and($output)->toContain('Passed: <fg=green>1</>')
                    ->and($output)->toContain('Failed: <fg=red>2</>')
                    ->and($output)->toContain('Pass Rate: 33.3%')
                    ->and($output)->toContain('Found 2 failure(s) during fuzzing:')
                    ->and($output)->toContain('1. fuzzing:test2:0:1')
                    ->and($output)->toContain('Description: invalid string as number')
                    ->and($output)->toContain('Error: <fg=red>Validation mismatch: expected false, got true</>')
                    ->and($output)->toContain('Duration: 0.0015s')
                    ->and($output)->toContain('2. fuzzing:test2:1:0')
                    ->and($output)->toContain('Description: maximum integer')
                    ->and($output)->toContain('Error: <fg=red>Integer overflow detected</>')
                    ->and($output)->toContain('Duration: 0.0020s')
                    ->and($output)->not->toContain('✓ All fuzzed tests passed!');
            });

            test('renders failure details when tests fail without errors', function (): void {
                // Arrange
                $results = [
                    TestResult::fail(
                        id: 'fuzzing:test3:0:0',
                        file: 'fuzz/test3.json',
                        group: 'Type Validation',
                        description: 'null value',
                        data: null,
                        expected: false,
                        actual: true,
                        error: null,
                        duration: 0.000_8,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'Null Error Suite',
                    results: $results,
                    duration: 0.000_8,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Total Fuzzed Tests: 1')
                    ->and($output)->toContain('Passed: <fg=green>0</>')
                    ->and($output)->toContain('Failed: <fg=red>1</>')
                    ->and($output)->toContain('Pass Rate: 0.0%')
                    ->and($output)->toContain('Found 1 failure(s) during fuzzing:')
                    ->and($output)->toContain('1. fuzzing:test3:0:0')
                    ->and($output)->toContain('Description: null value')
                    ->and($output)->toContain('Duration: 0.0008s')
                    ->and($output)->not->toContain('Error:');
            });

            test('handles all tests failing', function (): void {
                // Arrange
                $results = [
                    TestResult::fail(
                        id: 'fuzzing:fail1:0:0',
                        file: 'fuzz/fail.json',
                        group: 'Failures',
                        description: 'first failure',
                        data: 'test1',
                        expected: true,
                        actual: false,
                        error: 'Error 1',
                        duration: 0.000_5,
                    ),
                    TestResult::fail(
                        id: 'fuzzing:fail1:0:1',
                        file: 'fuzz/fail.json',
                        group: 'Failures',
                        description: 'second failure',
                        data: 'test2',
                        expected: true,
                        actual: false,
                        error: 'Error 2',
                        duration: 0.000_6,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'All Failures Suite',
                    results: $results,
                    duration: 0.001_1,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Total Fuzzed Tests: 2')
                    ->and($output)->toContain('Passed: <fg=green>0</>')
                    ->and($output)->toContain('Failed: <fg=red>2</>')
                    ->and($output)->toContain('Pass Rate: 0.0%')
                    ->and($output)->toContain('Found 2 failure(s) during fuzzing:')
                    ->and($output)->toContain('1. fuzzing:fail1:0:0')
                    ->and($output)->toContain('2. fuzzing:fail1:0:1');
            });

            test('calculates pass rate correctly for various ratios', function (): void {
                // Arrange
                $results = [
                    TestResult::pass('id1', 'file.json', 'group', 'pass1', 'data', true, 0.001),
                    TestResult::pass('id2', 'file.json', 'group', 'pass2', 'data', true, 0.001),
                    TestResult::pass('id3', 'file.json', 'group', 'pass3', 'data', true, 0.001),
                    TestResult::fail('id4', 'file.json', 'group', 'fail1', 'data', true, false, 'error', 0.001),
                ];

                $suite = new TestSuite(
                    name: 'Pass Rate Test',
                    results: $results,
                    duration: 0.004,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Total Fuzzed Tests: 4')
                    ->and($output)->toContain('Passed: <fg=green>3</>')
                    ->and($output)->toContain('Failed: <fg=red>1</>')
                    ->and($output)->toContain('Pass Rate: 75.0%');
            });

            test('filters only failed results in failure section', function (): void {
                // Arrange
                $results = [
                    TestResult::pass('pass:1', 'file.json', 'group', 'passing test 1', 'data', true, 0.001),
                    TestResult::fail('fail:1', 'file.json', 'group', 'failing test 1', 'data', true, false, 'error1', 0.002),
                    TestResult::pass('pass:2', 'file.json', 'group', 'passing test 2', 'data', true, 0.001),
                    TestResult::fail('fail:2', 'file.json', 'group', 'failing test 2', 'data', true, false, 'error2', 0.003),
                    TestResult::pass('pass:3', 'file.json', 'group', 'passing test 3', 'data', true, 0.001),
                ];

                $suite = new TestSuite(
                    name: 'Mixed Suite',
                    results: $results,
                    duration: 0.008,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Total Fuzzed Tests: 5')
                    ->and($output)->toContain('Passed: <fg=green>3</>')
                    ->and($output)->toContain('Failed: <fg=red>2</>')
                    ->and($output)->toContain('1. fail:1')
                    ->and($output)->toContain('Description: failing test 1')
                    ->and($output)->toContain('Error: <fg=red>error1</>')
                    ->and($output)->toContain('2. fail:2')
                    ->and($output)->toContain('Description: failing test 2')
                    ->and($output)->toContain('Error: <fg=red>error2</>')
                    ->and($output)->not->toContain('pass:1')
                    ->and($output)->not->toContain('pass:2')
                    ->and($output)->not->toContain('pass:3')
                    ->and($output)->not->toContain('passing test');
            });

            test('formats duration with correct precision', function (): void {
                // Arrange
                $results = [
                    TestResult::fail(
                        id: 'fuzzing:duration:0:0',
                        file: 'fuzz/duration.json',
                        group: 'Duration Tests',
                        description: 'very fast test',
                        data: 'data',
                        expected: true,
                        actual: false,
                        error: 'error',
                        duration: 0.000_1,
                    ),
                    TestResult::fail(
                        id: 'fuzzing:duration:0:1',
                        file: 'fuzz/duration.json',
                        group: 'Duration Tests',
                        description: 'slow test',
                        data: 'data',
                        expected: true,
                        actual: false,
                        error: 'error',
                        duration: 1.234_5,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'Duration Suite',
                    results: $results,
                    duration: 1.234_6,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Duration: 0.0001s')
                    ->and($output)->toContain('Duration: 1.2345s');
            });

            test('handles multiple failures with mixed error states', function (): void {
                // Arrange
                $results = [
                    TestResult::fail('id1', 'file.json', 'group', 'test with error', 'data', true, false, 'Error message', 0.001),
                    TestResult::fail('id2', 'file.json', 'group', 'test without error', 'data', true, false, null, 0.002),
                    TestResult::fail('id3', 'file.json', 'group', 'test with empty error', 'data', true, false, '', 0.003),
                ];

                $suite = new TestSuite(
                    name: 'Mixed Errors',
                    results: $results,
                    duration: 0.006,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('1. id1')
                    ->and($output)->toContain('Error: <fg=red>Error message</>')
                    ->and($output)->toContain('2. id2')
                    ->and($output)->toContain('3. id3');

                // Count how many times "Error:" appears
                $errorCount = mb_substr_count($output, 'Error: <fg=red>');
                expect($errorCount)->toBe(2); // Only id1 and id3 should have error lines
            });

            test('handles single failure correctly', function (): void {
                // Arrange
                $results = [
                    TestResult::fail(
                        id: 'fuzzing:single:0:0',
                        file: 'fuzz/single.json',
                        group: 'Single Failure',
                        description: 'the only failure',
                        data: 'test',
                        expected: true,
                        actual: false,
                        error: 'The error',
                        duration: 0.001_2,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'Single Failure',
                    results: $results,
                    duration: 0.001_2,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Total Fuzzed Tests: 1')
                    ->and($output)->toContain('Passed: <fg=green>0</>')
                    ->and($output)->toContain('Failed: <fg=red>1</>')
                    ->and($output)->toContain('Pass Rate: 0.0%')
                    ->and($output)->toContain('Found 1 failure(s) during fuzzing:')
                    ->and($output)->toContain('1. fuzzing:single:0:0');
            });
        });

        describe('edge cases', function (): void {
            test('handles special characters in test descriptions', function (): void {
                // Arrange
                $results = [
                    TestResult::fail(
                        id: 'fuzzing:special:0:0',
                        file: 'fuzz/special.json',
                        group: 'Special Characters <>&"\'',
                        description: 'Test with <tags> & "quotes" and \'apostrophes\'',
                        data: 'data',
                        expected: true,
                        actual: false,
                        error: 'Error with <brackets> and & ampersand',
                        duration: 0.000_1,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'Special Chars',
                    results: $results,
                    duration: 0.000_1,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Test with <tags> & "quotes" and \'apostrophes\'')
                    ->and($output)->toContain('Error with <brackets> and & ampersand');
            });

            test('handles very long test descriptions', function (): void {
                // Arrange
                $longDescription = str_repeat('This is a very long test description that goes on and on. ', 10);
                $results = [
                    TestResult::fail(
                        id: 'fuzzing:long:0:0',
                        file: 'fuzz/long.json',
                        group: 'Long Descriptions',
                        description: $longDescription,
                        data: 'data',
                        expected: true,
                        actual: false,
                        error: 'Error',
                        duration: 0.000_1,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'Long Desc',
                    results: $results,
                    duration: 0.000_1,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain($longDescription);
            });

            test('handles unicode characters in descriptions and errors', function (): void {
                // Arrange
                $results = [
                    TestResult::fail(
                        id: 'fuzzing:unicode:0:0',
                        file: 'fuzz/unicode.json',
                        group: 'Unicode Tests 🚀',
                        description: 'Test with emoji 😀 and unicode ñ ü é',
                        data: 'データ',
                        expected: true,
                        actual: false,
                        error: 'エラーメッセージ',
                        duration: 0.000_1,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'Unicode Suite',
                    results: $results,
                    duration: 0.000_1,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Test with emoji 😀 and unicode ñ ü é')
                    ->and($output)->toContain('エラーメッセージ');
            });

            test('handles zero duration', function (): void {
                // Arrange
                $results = [
                    TestResult::fail(
                        id: 'fuzzing:zero:0:0',
                        file: 'fuzz/zero.json',
                        group: 'Zero Duration',
                        description: 'instant test',
                        data: 'data',
                        expected: true,
                        actual: false,
                        error: 'error',
                        duration: 0.0,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'Zero Duration',
                    results: $results,
                    duration: 0.0,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Duration: 0.0000s');
            });

            test('handles very large test counts', function (): void {
                // Arrange
                $results = [];

                for ($i = 0; $i < 500; ++$i) {
                    $results[] = TestResult::pass('id'.$i, 'file.json', 'group', 'test'.$i, 'data', true, 0.001);
                }

                for ($i = 0; $i < 500; ++$i) {
                    $results[] = TestResult::fail('idf'.$i, 'file.json', 'group', 'test'.$i, 'data', true, false, 'error'.$i, 0.001);
                }

                $suite = new TestSuite(
                    name: 'Large Suite',
                    results: $results,
                    duration: 1.0,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Total Fuzzed Tests: 1000')
                    ->and($output)->toContain('Passed: <fg=green>500</>')
                    ->and($output)->toContain('Failed: <fg=red>500</>')
                    ->and($output)->toContain('Pass Rate: 50.0%')
                    ->and($output)->toContain('Found 500 failure(s) during fuzzing:')
                    ->and($output)->toContain('500. idf499'); // Last failure
            });

            test('handles newlines in error messages', function (): void {
                // Arrange
                $results = [
                    TestResult::fail(
                        id: 'fuzzing:newline:0:0',
                        file: 'fuzz/newline.json',
                        group: 'Newline Tests',
                        description: 'test with multiline error',
                        data: 'data',
                        expected: true,
                        actual: false,
                        error: "Line 1\nLine 2\nLine 3",
                        duration: 0.000_1,
                    ),
                ];

                $suite = new TestSuite(
                    name: 'Newline Suite',
                    results: $results,
                    duration: 0.000_1,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain("Line 1\nLine 2\nLine 3");
            });

            test('handles pass rate rounding correctly', function (): void {
                // Arrange - 1 passed out of 3 = 33.333...%
                $results = [
                    TestResult::pass('id1', 'file.json', 'group', 'pass', 'data', true, 0.001),
                    TestResult::fail('id2', 'file.json', 'group', 'fail1', 'data', true, false, 'error', 0.001),
                    TestResult::fail('id3', 'file.json', 'group', 'fail2', 'data', true, false, 'error', 0.001),
                ];

                $suite = new TestSuite(
                    name: 'Rounding Test',
                    results: $results,
                    duration: 0.003,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                expect($output)->toContain('Pass Rate: 33.3%');
            });

            test('verifies output structure has correct sections', function (): void {
                // Arrange
                $results = [
                    TestResult::fail('id1', 'file.json', 'group', 'failure', 'data', true, false, 'error', 0.001),
                ];

                $suite = new TestSuite(
                    name: 'Structure Test',
                    results: $results,
                    duration: 0.001,
                );

                // Act
                $output = $this->renderer->render($suite);

                // Assert
                // Verify header comes first
                expect($output)->toMatch('/^\\n<fg=cyan;options=bold>Fuzzing Test Results<\/>\\n\\n/')
                    // Verify stats section exists
                    ->and($output)->toContain('Total Fuzzed Tests:')
                    ->and($output)->toContain('Passed:')
                    ->and($output)->toContain('Failed:')
                    ->and($output)->toContain('Pass Rate:')
                    // Verify failures section exists
                    ->and($output)->toContain('Found 1 failure(s) during fuzzing:')
                    ->and($output)->toContain('1. id1')
                    ->and($output)->toContain('Description: failure')
                    ->and($output)->toContain('Error:')
                    ->and($output)->toContain('Duration:');
            });
        });
    });
});
