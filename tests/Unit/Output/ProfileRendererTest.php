<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Output\ProfileRenderer;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;

describe('ProfileRenderer', function (): void {
    describe('render()', function (): void {
        test('returns warning message when no suites provided', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $suites = [];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toBe("\n<fg=yellow>No tests to profile.</>\n");
        });

        test('returns warning message when suites have no test results', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $suites = [
                new TestSuite('Suite 1', [], 0.0),
                new TestSuite('Suite 2', [], 0.0),
            ];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toBe("\n<fg=yellow>No tests to profile.</>\n");
        });

        test('returns no slow tests message when limit is zero', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:1', 'file1.php', 'Group 1', 'Test 1', [], true, 0.5),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites, 0);

            // Assert
            expect($result)->toBe("\n<fg=yellow>No slow tests found.</>\n");
        });

        test('renders single test with proper formatting', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:suite:file:0:0', 'file1.php', 'Group 1', 'Test 1', [], true, 0.123_456),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toContain('<fg=cyan;options=bold>Performance Profile - Top 1 Slowest Tests</>')
                ->and($result)->toContain('123.46ms')
                ->and($result)->toContain('test:suite:file:0:0');
        });

        test('renders multiple tests sorted by duration descending', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:fast', 'file1.php', 'Group 1', 'Fast test', [], true, 0.001),
                TestResult::pass('test:slow', 'file2.php', 'Group 2', 'Slow test', [], true, 0.5),
                TestResult::pass('test:medium', 'file3.php', 'Group 3', 'Medium test', [], true, 0.1),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            $lines = explode("\n", $result);
            expect($result)->toContain('test:slow')
                ->and($result)->toContain('test:medium')
                ->and($result)->toContain('test:fast');

            // Verify slowest test is first
            $slowIndex = null;
            $mediumIndex = null;
            $fastIndex = null;

            foreach ($lines as $index => $line) {
                if (str_contains($line, 'test:slow')) {
                    $slowIndex = $index;
                }

                if (str_contains($line, 'test:medium')) {
                    $mediumIndex = $index;
                }

                if (!str_contains($line, 'test:fast')) {
                    continue;
                }

                $fastIndex = $index;
            }

            expect($slowIndex)->toBeLessThan($mediumIndex)
                ->and($mediumIndex)->toBeLessThan($fastIndex);
        });

        test('respects limit parameter to show only top N slowest tests', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:1', 'file1.php', 'Group 1', 'Test 1', [], true, 0.5),
                TestResult::pass('test:2', 'file2.php', 'Group 2', 'Test 2', [], true, 0.4),
                TestResult::pass('test:3', 'file3.php', 'Group 3', 'Test 3', [], true, 0.3),
                TestResult::pass('test:4', 'file4.php', 'Group 4', 'Test 4', [], true, 0.2),
                TestResult::pass('test:5', 'file5.php', 'Group 5', 'Test 5', [], true, 0.1),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites, 3);

            // Assert
            expect($result)->toContain('test:1')
                ->and($result)->toContain('test:2')
                ->and($result)->toContain('test:3')
                ->and($result)->not->toContain('test:4')
                ->and($result)->not->toContain('test:5')
                ->and($result)->toContain('Performance Profile - Top 3 Slowest Tests');
        });

        test('aggregates results from multiple test suites', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $suite1Results = [
                TestResult::pass('suite1:test:1', 'file1.php', 'Group 1', 'Test 1', [], true, 0.3),
                TestResult::pass('suite1:test:2', 'file2.php', 'Group 2', 'Test 2', [], true, 0.1),
            ];
            $suite2Results = [
                TestResult::pass('suite2:test:1', 'file3.php', 'Group 3', 'Test 3', [], true, 0.5),
                TestResult::pass('suite2:test:2', 'file4.php', 'Group 4', 'Test 4', [], true, 0.2),
            ];
            $suites = [
                new TestSuite('Suite 1', $suite1Results, 1.0),
                new TestSuite('Suite 2', $suite2Results, 1.0),
            ];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toContain('suite1:test:1')
                ->and($result)->toContain('suite1:test:2')
                ->and($result)->toContain('suite2:test:1')
                ->and($result)->toContain('suite2:test:2');

            // Verify sorting across suites
            $lines = explode("\n", $result);
            $suite2Test1Index = null;
            $suite1Test1Index = null;

            foreach ($lines as $index => $line) {
                if (str_contains($line, 'suite2:test:1')) {
                    $suite2Test1Index = $index;
                }

                if (!str_contains($line, 'suite1:test:1')) {
                    continue;
                }

                $suite1Test1Index = $index;
            }

            expect($suite2Test1Index)->toBeLessThan($suite1Test1Index);
        });

        test('formats duration with exactly 2 decimal places', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:1', 'file1.php', 'Group 1', 'Test 1', [], true, 0.123_456_789),
                TestResult::pass('test:2', 'file2.php', 'Group 2', 'Test 2', [], true, 0.1),
                TestResult::pass('test:3', 'file3.php', 'Group 3', 'Test 3', [], true, 1.999_999),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toContain('123.46ms') // Rounded up
                ->and($result)->toContain('100.00ms')
                ->and($result)->toContain('2,000.00ms'); // Rounded up with thousands separator
        });

        test('right-pads duration strings to align with longest duration', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:1', 'file1.php', 'Group 1', 'Test 1', [], true, 10.0), // 10,000.00ms (9 chars with comma)
                TestResult::pass('test:2', 'file2.php', 'Group 2', 'Test 2', [], true, 0.001), // 1.00ms (4 chars)
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            $lines = explode("\n", $result);
            $test1Line = null;
            $test2Line = null;

            foreach ($lines as $line) {
                if (str_contains($line, 'test:1')) {
                    $test1Line = $line;
                }

                if (!str_contains($line, 'test:2')) {
                    continue;
                }

                $test2Line = $line;
            }

            // Verify both durations are present with proper formatting
            expect($test1Line)->toContain('10,000.00ms')
                ->and($test1Line)->toContain('test:1')
                ->and($test2Line)->toContain('1.00ms')
                ->and($test2Line)->toContain('test:2');
        });

        test('uses default limit of 10 when not specified', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [];

            for ($i = 1; $i <= 20; ++$i) {
                $results[] = TestResult::pass('test:'.$i, 'file.php', 'Group', 'Test', [], true, (20 - $i) * 0.1);
            }

            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites); // No limit parameter

            // Assert
            expect($result)->toContain('Performance Profile - Top 10 Slowest Tests')
                ->and($result)->toContain('test:1')
                ->and($result)->toContain('test:10')
                ->and($result)->not->toContain('test:11');
        });

        test('handles zero duration tests', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:zero', 'file1.php', 'Group 1', 'Zero duration', [], true, 0.0),
                TestResult::pass('test:tiny', 'file2.php', 'Group 2', 'Tiny duration', [], true, 0.000_001),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toContain('0.00ms')
                ->and($result)->toContain('test:zero')
                ->and($result)->toContain('test:tiny');
        });

        test('handles very large durations', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:huge', 'file1.php', 'Group 1', 'Huge duration', [], true, 999.999),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toContain('999,999.00ms') // number_format adds comma for thousands
                ->and($result)->toContain('test:huge');
        });

        test('includes both passing and failing test results', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:pass', 'file1.php', 'Group 1', 'Passing test', [], true, 0.2),
                TestResult::fail('test:fail', 'file2.php', 'Group 2', 'Failing test', [], true, false, 'Error message', 0.3),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toContain('test:pass')
                ->and($result)->toContain('test:fail');
        });

        test('output starts with newline and header', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:1', 'file1.php', 'Group 1', 'Test 1', [], true, 0.1),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toStartWith("\n<fg=cyan;options=bold>Performance Profile - Top 1 Slowest Tests</>\n\n");
        });

        test('output ends with double newline', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:1', 'file1.php', 'Group 1', 'Test 1', [], true, 0.1),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toEndWith("\n\n");
        });

        test('each test line has proper indentation', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:1', 'file1.php', 'Group 1', 'Test 1', [], true, 0.1),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toMatch('/\n  <fg=yellow>\d+\.\d{2}ms<\/> test:1\n/');
        });

        test('handles test IDs with special characters', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:suite:file-name_with.special:0:0', 'file.php', 'Group', 'Test', [], true, 0.1),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toContain('test:suite:file-name_with.special:0:0');
        });

        test('handles multibyte characters in test IDs', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:日本語:émoji:🚀', 'file.php', 'Group', 'Test', [], true, 0.1),
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toContain('test:日本語:émoji:🚀');
        });

        test('calculates max duration width correctly for alignment', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:1', 'file1.php', 'Group', 'Test', [], true, 1.0), // 1,000.00 (8 chars)
                TestResult::pass('test:2', 'file2.php', 'Group', 'Test', [], true, 0.1), // 100.00 (6 chars)
                TestResult::pass('test:3', 'file3.php', 'Group', 'Test', [], true, 10.0), // 10,000.00 (9 chars)
            ];
            $suites = [new TestSuite('Suite 1', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            $lines = array_filter(explode("\n", $result), fn ($line): bool => str_contains($line, 'ms'));

            // All duration strings should be padded to 9 characters (the max width with comma)
            foreach ($lines as $line) {
                // Extract the duration part between yellow tags (including comma)
                if (!preg_match('/<fg=yellow>(\s*[\d,]+\.\d{2})ms<\/>/', $line, $matches)) {
                    continue;
                }

                $durationStr = $matches[1];
                // Length should include padding
                expect(mb_strlen($durationStr))->toBe(9);
            }
        });

        test('renders exact output format for single test', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:id', 'file.php', 'Group', 'Description', [], true, 0.123),
            ];
            $suites = [new TestSuite('Suite', $results, 1.0)];

            // Act
            $result = $renderer->render($suites, 10);

            // Assert
            $expected = "\n<fg=cyan;options=bold>Performance Profile - Top 1 Slowest Tests</>\n\n"
                ."  <fg=yellow>123.00ms</> test:id\n"
                ."\n";
            expect($result)->toBe($expected);
        });

        test('handles empty suite mixed with non-empty suites', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $suites = [
                new TestSuite('Empty Suite', [], 0.0),
                new TestSuite('Suite with tests', [
                    TestResult::pass('test:1', 'file.php', 'Group', 'Test', [], true, 0.1),
                ], 1.0),
                new TestSuite('Another empty', [], 0.0),
            ];

            // Act
            $result = $renderer->render($suites);

            // Assert
            expect($result)->toContain('test:1')
                ->and($result)->toContain('Performance Profile - Top 1 Slowest Tests');
        });

        test('limit larger than available tests shows all tests', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:1', 'file1.php', 'Group', 'Test', [], true, 0.3),
                TestResult::pass('test:2', 'file2.php', 'Group', 'Test', [], true, 0.2),
            ];
            $suites = [new TestSuite('Suite', $results, 1.0)];

            // Act
            $result = $renderer->render($suites, 100);

            // Assert
            expect($result)->toContain('test:1')
                ->and($result)->toContain('test:2')
                ->and($result)->toContain('Performance Profile - Top 2 Slowest Tests');
        });

        test('sorts tests with identical durations consistently', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:a', 'file1.php', 'Group', 'Test A', [], true, 0.1),
                TestResult::pass('test:b', 'file2.php', 'Group', 'Test B', [], true, 0.1),
                TestResult::pass('test:c', 'file3.php', 'Group', 'Test C', [], true, 0.1),
            ];
            $suites = [new TestSuite('Suite', $results, 1.0)];

            // Act
            $result = $renderer->render($suites);

            // Assert
            // All three should be present
            expect($result)->toContain('test:a')
                ->and($result)->toContain('test:b')
                ->and($result)->toContain('test:c');
        });

        test('handles limit of 1 correctly', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:slow', 'file1.php', 'Group', 'Slow', [], true, 0.5),
                TestResult::pass('test:fast', 'file2.php', 'Group', 'Fast', [], true, 0.1),
            ];
            $suites = [new TestSuite('Suite', $results, 1.0)];

            // Act
            $result = $renderer->render($suites, 1);

            // Assert
            expect($result)->toContain('test:slow')
                ->and($result)->not->toContain('test:fast')
                ->and($result)->toContain('Performance Profile - Top 1 Slowest Tests');
        });

        test('negative limit treated as zero shows no slow tests message', function (): void {
            // Arrange
            $renderer = new ProfileRenderer();
            $results = [
                TestResult::pass('test:1', 'file.php', 'Group', 'Test', [], true, 0.1),
            ];
            $suites = [new TestSuite('Suite', $results, 1.0)];

            // Act
            $result = $renderer->render($suites, -5);

            // Assert
            expect($result)->toBe("\n<fg=yellow>No slow tests found.</>\n");
        });
    });
});
