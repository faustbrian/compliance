<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Services\BenchmarkService;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;

describe('BenchmarkService', function (): void {
    beforeEach(function (): void {
        // Use a temporary directory for testing
        $this->testBaselineDir = sys_get_temp_dir().'/prism-test-baselines-'.uniqid();
        $this->service = new BenchmarkService($this->testBaselineDir);
    });

    afterEach(function (): void {
        // Clean up test baseline directory and files
        if (!is_dir($this->testBaselineDir)) {
            return;
        }

        $files = glob($this->testBaselineDir.'/*.json');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->testBaselineDir);
    });

    describe('constructor', function (): void {
        test('uses default baseline directory when none provided', function (): void {
            // Arrange
            $expectedDir = getcwd().'/.prism/baselines';

            // Act
            $service = new BenchmarkService();

            // Assert
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('baselineDir');

            $actualDir = $property->getValue($service);

            expect($actualDir)->toBe($expectedDir);
        });

        test('uses custom baseline directory when provided', function (): void {
            // Arrange
            $customDir = '/custom/baseline/path';

            // Act
            $service = new BenchmarkService($customDir);

            // Assert
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('baselineDir');

            $actualDir = $property->getValue($service);

            expect($actualDir)->toBe($customDir);
        });

        test('handles getcwd returning false by using current directory', function (): void {
            // Note: This is a defensive test for the ternary operator in constructor
            // In normal circumstances, getcwd() should not return false in this context
            // But the code has protection: (getcwd() ?: '.')
            $service = new BenchmarkService();

            expect($service)->toBeInstanceOf(BenchmarkService::class);
        });
    });

    describe('saveBaseline', function (): void {
        test('creates baseline directory when it does not exist', function (): void {
            // Arrange
            expect(is_dir($this->testBaselineDir))->toBeFalse();

            $testResult = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test',
                data: [],
                expected: true,
                duration: 0.5,
            );

            $suite = new TestSuite(
                name: 'Test Suite',
                results: [$testResult],
                duration: 1.0,
            );

            // Act
            $this->service->saveBaseline([$suite]);

            // Assert
            expect(is_dir($this->testBaselineDir))->toBeTrue();
        });

        test('saves baseline with default name when no name provided', function (): void {
            // Arrange
            $testResult = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test',
                data: [],
                expected: true,
                duration: 0.5,
            );

            $suite = new TestSuite(
                name: 'Test Suite',
                results: [$testResult],
                duration: 1.0,
            );

            // Act
            $this->service->saveBaseline([$suite]);

            // Assert
            $baselinePath = $this->testBaselineDir.'/default.json';
            expect(file_exists($baselinePath))->toBeTrue();
        });

        test('saves baseline with custom name when provided', function (): void {
            // Arrange
            $testResult = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test',
                data: [],
                expected: true,
                duration: 0.5,
            );

            $suite = new TestSuite(
                name: 'Test Suite',
                results: [$testResult],
                duration: 1.0,
            );

            // Act
            $this->service->saveBaseline([$suite], 'production');

            // Assert
            $baselinePath = $this->testBaselineDir.'/production.json';
            expect(file_exists($baselinePath))->toBeTrue();
        });

        test('saves baseline with correct JSON structure for single suite', function (): void {
            // Arrange
            $testResult = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test',
                data: [],
                expected: true,
                duration: 0.5,
            );

            $suite = new TestSuite(
                name: 'Test Suite',
                results: [$testResult],
                duration: 1.0,
            );

            // Act
            $this->service->saveBaseline([$suite]);

            // Assert
            $baselinePath = $this->testBaselineDir.'/default.json';
            $contents = file_get_contents($baselinePath);
            $data = json_decode($contents, true);

            expect($data)->toHaveKey('Test Suite')
                ->and($data['Test Suite'])->toHaveKey('total_duration')
                ->and($data['Test Suite']['total_duration'])->toEqual(1.0)
                ->and($data['Test Suite'])->toHaveKey('total_tests')
                ->and($data['Test Suite']['total_tests'])->toBe(1)
                ->and($data['Test Suite'])->toHaveKey('test_timings')
                ->and($data['Test Suite']['test_timings'])->toEqual(['suite:file:0:0' => 0.5]);
        });

        test('saves baseline with multiple test results in single suite', function (): void {
            // Arrange
            $testResult1 = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test 1',
                data: [],
                expected: true,
                duration: 0.3,
            );

            $testResult2 = TestResult::pass(
                id: 'suite:file:0:1',
                file: 'test.php',
                group: 'Group',
                description: 'test 2',
                data: [],
                expected: true,
                duration: 0.7,
            );

            $suite = new TestSuite(
                name: 'Multi-Test Suite',
                results: [$testResult1, $testResult2],
                duration: 1.0,
            );

            // Act
            $this->service->saveBaseline([$suite]);

            // Assert
            $baselinePath = $this->testBaselineDir.'/default.json';
            $contents = file_get_contents($baselinePath);
            $data = json_decode($contents, true);

            expect($data['Multi-Test Suite']['total_tests'])->toBe(2)
                ->and($data['Multi-Test Suite']['test_timings'])->toBe([
                    'suite:file:0:0' => 0.3,
                    'suite:file:0:1' => 0.7,
                ]);
        });

        test('saves baseline with multiple suites', function (): void {
            // Arrange
            $testResult1 = TestResult::pass(
                id: 'suite1:file:0:0',
                file: 'test1.php',
                group: 'Group1',
                description: 'test 1',
                data: [],
                expected: true,
                duration: 0.5,
            );

            $testResult2 = TestResult::pass(
                id: 'suite2:file:0:0',
                file: 'test2.php',
                group: 'Group2',
                description: 'test 2',
                data: [],
                expected: true,
                duration: 0.8,
            );

            $suite1 = new TestSuite(
                name: 'Suite 1',
                results: [$testResult1],
                duration: 0.5,
            );

            $suite2 = new TestSuite(
                name: 'Suite 2',
                results: [$testResult2],
                duration: 0.8,
            );

            // Act
            $this->service->saveBaseline([$suite1, $suite2]);

            // Assert
            $baselinePath = $this->testBaselineDir.'/default.json';
            $contents = file_get_contents($baselinePath);
            $data = json_decode($contents, true);

            expect($data)->toHaveKey('Suite 1')
                ->and($data)->toHaveKey('Suite 2')
                ->and($data['Suite 1']['total_duration'])->toBe(0.5)
                ->and($data['Suite 2']['total_duration'])->toBe(0.8)
                ->and($data['Suite 1']['total_tests'])->toBe(1)
                ->and($data['Suite 2']['total_tests'])->toBe(1);
        });

        test('saves baseline with empty suite results array', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'Empty Suite',
                results: [],
                duration: 0.0,
            );

            // Act
            $this->service->saveBaseline([$suite]);

            // Assert
            $baselinePath = $this->testBaselineDir.'/default.json';
            $contents = file_get_contents($baselinePath);
            $data = json_decode($contents, true);

            expect($data['Empty Suite']['total_tests'])->toBe(0)
                ->and($data['Empty Suite']['test_timings'])->toBe([]);
        });

        test('saves baseline with empty suites array', function (): void {
            // Arrange & Act
            $this->service->saveBaseline([]);

            // Assert
            $baselinePath = $this->testBaselineDir.'/default.json';
            $contents = file_get_contents($baselinePath);
            $data = json_decode($contents, true);

            expect($data)->toBe([]);
        });

        test('overwrites existing baseline file with same name', function (): void {
            // Arrange
            $testResult1 = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test 1',
                data: [],
                expected: true,
                duration: 0.5,
            );

            $suite1 = new TestSuite(
                name: 'Suite 1',
                results: [$testResult1],
                duration: 0.5,
            );

            $this->service->saveBaseline([$suite1]);

            $testResult2 = TestResult::pass(
                id: 'suite:file:0:1',
                file: 'test.php',
                group: 'Group',
                description: 'test 2',
                data: [],
                expected: true,
                duration: 1.0,
            );

            $suite2 = new TestSuite(
                name: 'Suite 2',
                results: [$testResult2],
                duration: 1.0,
            );

            // Act
            $this->service->saveBaseline([$suite2]);

            // Assert
            $baselinePath = $this->testBaselineDir.'/default.json';
            $contents = file_get_contents($baselinePath);
            $data = json_decode($contents, true);

            expect($data)->toHaveKey('Suite 2')
                ->and($data)->not->toHaveKey('Suite 1');
        });

        test('saves baseline with pretty-printed JSON format', function (): void {
            // Arrange
            $testResult = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test',
                data: [],
                expected: true,
                duration: 0.5,
            );

            $suite = new TestSuite(
                name: 'Test Suite',
                results: [$testResult],
                duration: 1.0,
            );

            // Act
            $this->service->saveBaseline([$suite]);

            // Assert
            $baselinePath = $this->testBaselineDir.'/default.json';
            $contents = file_get_contents($baselinePath);

            // Pretty-printed JSON should contain newlines and indentation
            expect($contents)->toContain("\n")
                ->and($contents)->toContain('    ');
        });

        test('does not create baseline directory when it already exists', function (): void {
            // Arrange
            mkdir($this->testBaselineDir, 0o755, true);
            $originalMode = fileperms($this->testBaselineDir);

            $testResult = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test',
                data: [],
                expected: true,
                duration: 0.5,
            );

            $suite = new TestSuite(
                name: 'Test Suite',
                results: [$testResult],
                duration: 1.0,
            );

            // Act
            $this->service->saveBaseline([$suite]);

            // Assert
            expect(is_dir($this->testBaselineDir))->toBeTrue()
                ->and(fileperms($this->testBaselineDir))->toBe($originalMode);
        });

        test('extracts test timings from all results in foreach loop', function (): void {
            // Arrange - This specifically tests lines 74-76 (the inner foreach)
            $testResults = [
                TestResult::pass(
                    id: 'suite:file:0:0',
                    file: 'test.php',
                    group: 'Group',
                    description: 'test 1',
                    data: [],
                    expected: true,
                    duration: 0.1,
                ),
                TestResult::pass(
                    id: 'suite:file:0:1',
                    file: 'test.php',
                    group: 'Group',
                    description: 'test 2',
                    data: [],
                    expected: true,
                    duration: 0.2,
                ),
                TestResult::pass(
                    id: 'suite:file:0:2',
                    file: 'test.php',
                    group: 'Group',
                    description: 'test 3',
                    data: [],
                    expected: true,
                    duration: 0.3,
                ),
            ];

            $suite = new TestSuite(
                name: 'Test Suite',
                results: $testResults,
                duration: 0.6,
            );

            // Act
            $this->service->saveBaseline([$suite]);

            // Assert
            $baselinePath = $this->testBaselineDir.'/default.json';
            $contents = file_get_contents($baselinePath);
            $data = json_decode($contents, true);

            expect($data['Test Suite']['test_timings'])->toBe([
                'suite:file:0:0' => 0.1,
                'suite:file:0:1' => 0.2,
                'suite:file:0:2' => 0.3,
            ]);
        });

        test('processes multiple suites in outer foreach loop', function (): void {
            // Arrange - This specifically tests lines 71-82 (the outer foreach)
            $suites = [
                new TestSuite(
                    name: 'Suite A',
                    results: [
                        TestResult::pass(
                            id: 'a:file:0:0',
                            file: 'test_a.php',
                            group: 'GroupA',
                            description: 'test a',
                            data: [],
                            expected: true,
                            duration: 0.1,
                        ),
                    ],
                    duration: 0.1,
                ),
                new TestSuite(
                    name: 'Suite B',
                    results: [
                        TestResult::pass(
                            id: 'b:file:0:0',
                            file: 'test_b.php',
                            group: 'GroupB',
                            description: 'test b',
                            data: [],
                            expected: true,
                            duration: 0.2,
                        ),
                    ],
                    duration: 0.2,
                ),
                new TestSuite(
                    name: 'Suite C',
                    results: [
                        TestResult::pass(
                            id: 'c:file:0:0',
                            file: 'test_c.php',
                            group: 'GroupC',
                            description: 'test c',
                            data: [],
                            expected: true,
                            duration: 0.3,
                        ),
                    ],
                    duration: 0.3,
                ),
            ];

            // Act
            $this->service->saveBaseline($suites);

            // Assert
            $baselinePath = $this->testBaselineDir.'/default.json';
            $contents = file_get_contents($baselinePath);
            $data = json_decode($contents, true);

            expect($data)->toHaveKeys(['Suite A', 'Suite B', 'Suite C'])
                ->and($data['Suite A']['total_duration'])->toBe(0.1)
                ->and($data['Suite B']['total_duration'])->toBe(0.2)
                ->and($data['Suite C']['total_duration'])->toBe(0.3)
                ->and($data['Suite A']['total_tests'])->toBe(1)
                ->and($data['Suite B']['total_tests'])->toBe(1)
                ->and($data['Suite C']['total_tests'])->toBe(1);
        });
    });

    describe('loadBaseline', function (): void {
        test('loads baseline with default name when no name provided', function (): void {
            // Arrange
            $testResult = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test',
                data: [],
                expected: true,
                duration: 0.5,
            );

            $suite = new TestSuite(
                name: 'Test Suite',
                results: [$testResult],
                duration: 1.0,
            );

            $this->service->saveBaseline([$suite]);

            // Act
            $baseline = $this->service->loadBaseline();

            // Assert
            expect($baseline)->toBeArray()
                ->and($baseline)->toHaveKey('Test Suite')
                ->and($baseline['Test Suite']['total_duration'])->toEqual(1.0)
                ->and($baseline['Test Suite']['total_tests'])->toBe(1);
        });

        test('loads baseline with custom name when provided', function (): void {
            // Arrange
            $testResult = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test',
                data: [],
                expected: true,
                duration: 0.5,
            );

            $suite = new TestSuite(
                name: 'Test Suite',
                results: [$testResult],
                duration: 1.0,
            );

            $this->service->saveBaseline([$suite], 'production');

            // Act
            $baseline = $this->service->loadBaseline('production');

            // Assert
            expect($baseline)->toBeArray()
                ->and($baseline)->toHaveKey('Test Suite');
        });

        test('returns null when baseline file does not exist', function (): void {
            // Arrange & Act
            $baseline = $this->service->loadBaseline('nonexistent');

            // Assert
            expect($baseline)->toBeNull();
        });

        test('handles corrupted baseline file gracefully', function (): void {
            // Arrange
            // This test verifies proper error handling when file operations fail
            // The file_get_contents === false check (line 113) is defensive programming
            mkdir($this->testBaselineDir, 0o755, true);
            $baselinePath = $this->testBaselineDir.'/empty.json';

            // Create an empty file (0 bytes) - valid for JSON decode but edge case
            touch($baselinePath);

            // Act
            $baseline = $this->service->loadBaseline('empty');

            // Assert - Empty file decodes to null, which triggers the is_array check
            expect($baseline)->toBeNull();
        });

        test('returns null when baseline file contains invalid JSON', function (): void {
            // Arrange
            mkdir($this->testBaselineDir, 0o755, true);
            $baselinePath = $this->testBaselineDir.'/invalid.json';
            file_put_contents($baselinePath, 'invalid json content');

            // Act
            $baseline = $this->service->loadBaseline('invalid');

            // Assert
            expect($baseline)->toBeNull();
        });

        test('returns null when baseline file contains non-array JSON', function (): void {
            // Arrange
            mkdir($this->testBaselineDir, 0o755, true);
            $baselinePath = $this->testBaselineDir.'/string.json';
            file_put_contents($baselinePath, '"just a string"');

            // Act
            $baseline = $this->service->loadBaseline('string');

            // Assert
            expect($baseline)->toBeNull();
        });

        test('loads baseline with multiple suites correctly', function (): void {
            // Arrange
            $suite1 = new TestSuite(
                name: 'Suite 1',
                results: [
                    TestResult::pass(
                        id: 'suite1:file:0:0',
                        file: 'test1.php',
                        group: 'Group1',
                        description: 'test 1',
                        data: [],
                        expected: true,
                        duration: 0.5,
                    ),
                ],
                duration: 0.5,
            );

            $suite2 = new TestSuite(
                name: 'Suite 2',
                results: [
                    TestResult::pass(
                        id: 'suite2:file:0:0',
                        file: 'test2.php',
                        group: 'Group2',
                        description: 'test 2',
                        data: [],
                        expected: true,
                        duration: 0.8,
                    ),
                ],
                duration: 0.8,
            );

            $this->service->saveBaseline([$suite1, $suite2]);

            // Act
            $baseline = $this->service->loadBaseline();

            // Assert
            expect($baseline)->toBeArray()
                ->and($baseline)->toHaveKey('Suite 1')
                ->and($baseline)->toHaveKey('Suite 2')
                ->and($baseline['Suite 1']['total_duration'])->toBe(0.5)
                ->and($baseline['Suite 2']['total_duration'])->toBe(0.8);
        });

        test('loads baseline with test timings correctly', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass(
                        id: 'suite:file:0:0',
                        file: 'test.php',
                        group: 'Group',
                        description: 'test 1',
                        data: [],
                        expected: true,
                        duration: 0.3,
                    ),
                    TestResult::pass(
                        id: 'suite:file:0:1',
                        file: 'test.php',
                        group: 'Group',
                        description: 'test 2',
                        data: [],
                        expected: true,
                        duration: 0.7,
                    ),
                ],
                duration: 1.0,
            );

            $this->service->saveBaseline([$suite]);

            // Act
            $baseline = $this->service->loadBaseline();

            // Assert
            expect($baseline['Test Suite']['test_timings'])->toBe([
                'suite:file:0:0' => 0.3,
                'suite:file:0:1' => 0.7,
            ]);
        });

        test('loads empty baseline correctly', function (): void {
            // Arrange
            $this->service->saveBaseline([]);

            // Act
            $baseline = $this->service->loadBaseline();

            // Assert
            expect($baseline)->toBeArray()
                ->and($baseline)->toBe([]);
        });
    });

    describe('baseline directory management', function (): void {
        test('creates baseline directory with correct permissions', function (): void {
            // Arrange
            expect(is_dir($this->testBaselineDir))->toBeFalse();

            $suite = new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass(
                        id: 'suite:file:0:0',
                        file: 'test.php',
                        group: 'Group',
                        description: 'test',
                        data: [],
                        expected: true,
                        duration: 0.5,
                    ),
                ],
                duration: 1.0,
            );

            // Act
            $this->service->saveBaseline([$suite]);

            // Assert
            expect(is_dir($this->testBaselineDir))->toBeTrue();
            $permissions = mb_substr(sprintf('%o', fileperms($this->testBaselineDir)), -4);
            // Check that directory is readable, writable, and executable
            expect($permissions)->toMatch('/07[5-7][5-7]/'); // 0755 or more permissive
        });

        test('creates nested baseline directory structure', function (): void {
            // Arrange
            $nestedDir = sys_get_temp_dir().'/prism-nested-'.uniqid().'/deep/structure';
            $service = new BenchmarkService($nestedDir);

            $suite = new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass(
                        id: 'suite:file:0:0',
                        file: 'test.php',
                        group: 'Group',
                        description: 'test',
                        data: [],
                        expected: true,
                        duration: 0.5,
                    ),
                ],
                duration: 1.0,
            );

            // Act
            $service->saveBaseline([$suite]);

            // Assert
            expect(is_dir($nestedDir))->toBeTrue()
                ->and(file_exists($nestedDir.'/default.json'))->toBeTrue();

            // Cleanup
            unlink($nestedDir.'/default.json');
            rmdir($nestedDir);
            rmdir(dirname($nestedDir));
            rmdir(dirname($nestedDir, 2));
        });
    });

    describe('edge cases', function (): void {
        test('handles suite with zero duration', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'Zero Duration Suite',
                results: [
                    TestResult::pass(
                        id: 'suite:file:0:0',
                        file: 'test.php',
                        group: 'Group',
                        description: 'test',
                        data: [],
                        expected: true,
                        duration: 0.0,
                    ),
                ],
                duration: 0.0,
            );

            // Act
            $this->service->saveBaseline([$suite]);
            $baseline = $this->service->loadBaseline();

            // Assert
            expect($baseline['Zero Duration Suite']['total_duration'])->toEqual(0.0)
                ->and($baseline['Zero Duration Suite']['test_timings']['suite:file:0:0'])->toEqual(0.0);
        });

        test('handles suite names with special characters', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'Suite: with/special\\chars & symbols!',
                results: [
                    TestResult::pass(
                        id: 'suite:file:0:0',
                        file: 'test.php',
                        group: 'Group',
                        description: 'test',
                        data: [],
                        expected: true,
                        duration: 0.5,
                    ),
                ],
                duration: 1.0,
            );

            // Act
            $this->service->saveBaseline([$suite]);
            $baseline = $this->service->loadBaseline();

            // Assert
            expect($baseline)->toHaveKey('Suite: with/special\\chars & symbols!')
                ->and($baseline['Suite: with/special\\chars & symbols!']['total_tests'])->toBe(1);
        });

        test('handles very large number of test results', function (): void {
            // Arrange
            $results = [];

            for ($i = 0; $i < 1_000; ++$i) {
                $results[] = TestResult::pass(
                    id: sprintf('suite:file:0:%d', $i),
                    file: 'test.php',
                    group: 'Group',
                    description: sprintf('test %d', $i),
                    data: [],
                    expected: true,
                    duration: 0.001 * $i,
                );
            }

            $suite = new TestSuite(
                name: 'Large Suite',
                results: $results,
                duration: 500.0,
            );

            // Act
            $this->service->saveBaseline([$suite]);
            $baseline = $this->service->loadBaseline();

            // Assert
            expect($baseline['Large Suite']['total_tests'])->toBe(1_000)
                ->and($baseline['Large Suite']['test_timings'])->toHaveCount(1_000);
        });

        test('handles very large number of suites', function (): void {
            // Arrange
            $suites = [];

            for ($i = 0; $i < 100; ++$i) {
                $suites[] = new TestSuite(
                    name: sprintf('Suite %d', $i),
                    results: [
                        TestResult::pass(
                            id: sprintf('suite%d:file:0:0', $i),
                            file: 'test.php',
                            group: 'Group',
                            description: 'test',
                            data: [],
                            expected: true,
                            duration: 0.1,
                        ),
                    ],
                    duration: 0.1,
                );
            }

            // Act
            $this->service->saveBaseline($suites);
            $baseline = $this->service->loadBaseline();

            // Assert
            expect($baseline)->toHaveCount(100)
                ->and($baseline)->toHaveKey('Suite 0')
                ->and($baseline)->toHaveKey('Suite 99');
        });

        test('handles baseline name with special characters', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass(
                        id: 'suite:file:0:0',
                        file: 'test.php',
                        group: 'Group',
                        description: 'test',
                        data: [],
                        expected: true,
                        duration: 0.5,
                    ),
                ],
                duration: 1.0,
            );

            // Act
            $this->service->saveBaseline([$suite], 'test-baseline_v1.2');
            $baseline = $this->service->loadBaseline('test-baseline_v1.2');

            // Assert
            expect($baseline)->toBeArray()
                ->and($baseline)->toHaveKey('Test Suite');
        });

        test('handles test result with negative duration gracefully', function (): void {
            // Arrange - defensive test for unusual data
            $suite = new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass(
                        id: 'suite:file:0:0',
                        file: 'test.php',
                        group: 'Group',
                        description: 'test',
                        data: [],
                        expected: true,
                        duration: -0.5,
                    ),
                ],
                duration: -0.5,
            );

            // Act
            $this->service->saveBaseline([$suite]);
            $baseline = $this->service->loadBaseline();

            // Assert
            expect($baseline['Test Suite']['total_duration'])->toBe(-0.5)
                ->and($baseline['Test Suite']['test_timings']['suite:file:0:0'])->toBe(-0.5);
        });

        test('handles test result with very large duration', function (): void {
            // Arrange
            $largeDuration = 999_999.99;
            $suite = new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass(
                        id: 'suite:file:0:0',
                        file: 'test.php',
                        group: 'Group',
                        description: 'test',
                        data: [],
                        expected: true,
                        duration: $largeDuration,
                    ),
                ],
                duration: $largeDuration,
            );

            // Act
            $this->service->saveBaseline([$suite]);
            $baseline = $this->service->loadBaseline();

            // Assert
            expect($baseline['Test Suite']['total_duration'])->toBe($largeDuration)
                ->and($baseline['Test Suite']['test_timings']['suite:file:0:0'])->toBe($largeDuration);
        });

        test('handles floating point precision in durations', function (): void {
            // Arrange
            $precisionDuration = 0.123_456_789;
            $suite = new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass(
                        id: 'suite:file:0:0',
                        file: 'test.php',
                        group: 'Group',
                        description: 'test',
                        data: [],
                        expected: true,
                        duration: $precisionDuration,
                    ),
                ],
                duration: $precisionDuration,
            );

            // Act
            $this->service->saveBaseline([$suite]);
            $baseline = $this->service->loadBaseline();

            // Assert
            expect($baseline['Test Suite']['total_duration'])->toBe($precisionDuration)
                ->and($baseline['Test Suite']['test_timings']['suite:file:0:0'])->toBe($precisionDuration);
        });
    });
});
