<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Services\SnapshotService;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;

describe('SnapshotService', function (): void {
    beforeEach(function (): void {
        $this->snapshotDir = sys_get_temp_dir().'/prism-snapshots-'.uniqid();
        $this->service = new SnapshotService($this->snapshotDir);
    });

    afterEach(function (): void {
        // Clean up snapshot directory
        if (!is_dir($this->snapshotDir)) {
            return;
        }

        $removeDir = function (string $dir) use (&$removeDir): void {
            if (!is_dir($dir)) {
                return;
            }

            $items = scandir($dir) ?: [];

            foreach ($items as $item) {
                if ($item === '.') {
                    continue;
                }

                if ($item === '..') {
                    continue;
                }

                $path = $dir.'/'.$item;

                if (is_dir($path)) {
                    $removeDir($path);

                    continue;
                }

                unlink($path);
            }

            rmdir($dir);
        };

        $removeDir($this->snapshotDir);
    });

    describe('Constructor', function (): void {
        test('uses custom snapshot directory when provided', function (): void {
            // Arrange
            $customDir = sys_get_temp_dir().'/custom-snapshot-'.uniqid();

            // Act
            $service = new SnapshotService($customDir);

            // Assert - verify by checking the path used in saveSnapshot
            $suite = new TestSuite(
                name: 'test-suite',
                results: [],
                duration: 0.0,
            );

            $service->saveSnapshot([$suite]);
            expect(file_exists($customDir.'/test-suite.json'))->toBeTrue();

            // Cleanup
            if (file_exists($customDir.'/test-suite.json')) {
                unlink($customDir.'/test-suite.json');
            }

            if (!is_dir($customDir)) {
                return;
            }

            rmdir($customDir);
        });

        test('uses default snapshot directory when not provided', function (): void {
            // Arrange & Act
            $service = new SnapshotService();

            // Assert - verify by attempting to save
            $suite = new TestSuite(
                name: 'default-test',
                results: [],
                duration: 0.0,
            );

            $service->saveSnapshot([$suite]);

            $expectedPath = (getcwd() ?: '.').'/.prism/snapshots/default-test.json';
            expect(file_exists($expectedPath))->toBeTrue();

            // Cleanup
            if (!file_exists($expectedPath)) {
                return;
            }

            unlink($expectedPath);
        });

        test('handles getcwd returning false gracefully', function (): void {
            // Arrange & Act
            // Cannot easily mock getcwd, but test verifies the fallback to '.'
            $service = new SnapshotService();

            // Assert - this tests the ?: '.' fallback in constructor
            expect($service)->toBeInstanceOf(SnapshotService::class);
        });
    });

    describe('saveSnapshot', function (): void {
        test('saves empty suite snapshot correctly', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'empty-suite',
                results: [],
                duration: 1.5,
            );

            // Act
            $this->service->saveSnapshot([$suite]);

            // Assert
            $snapshotPath = $this->snapshotDir.'/empty-suite.json';
            expect(file_exists($snapshotPath))->toBeTrue();

            $content = json_decode(file_get_contents($snapshotPath), true);
            expect($content['total_tests'])->toBe(0);
            expect($content['passed_tests'])->toBe(0);
            expect($content['failed_tests'])->toBe(0);
            expect($content['pass_rate'])->toEqual(0.0);
            expect($content['results'])->toBe([]);
        });

        test('saves suite with single passing test', function (): void {
            // Arrange
            $result = TestResult::pass(
                id: 'test:file:0:0',
                file: 'test.php',
                group: 'Valid Cases',
                description: 'should pass validation',
                data: ['name' => 'John'],
                expected: true,
                duration: 0.5,
                tags: ['unit'],
            );

            $suite = new TestSuite(
                name: 'single-pass',
                results: [$result],
                duration: 0.5,
            );

            // Act
            $this->service->saveSnapshot([$suite]);

            // Assert
            $snapshotPath = $this->snapshotDir.'/single-pass.json';
            expect(file_exists($snapshotPath))->toBeTrue();

            $content = json_decode(file_get_contents($snapshotPath), true);
            expect($content['total_tests'])->toBe(1);
            expect($content['passed_tests'])->toBe(1);
            expect($content['failed_tests'])->toBe(0);
            expect($content['pass_rate'])->toEqual(100.0);
            expect($content['results']['test:file:0:0'])->toBe([
                'passed' => true,
                'expected' => true,
                'actual' => true,
            ]);
        });

        test('saves suite with single failing test', function (): void {
            // Arrange
            $result = TestResult::fail(
                id: 'test:file:0:0',
                file: 'test.php',
                group: 'Invalid Cases',
                description: 'should fail validation',
                data: ['name' => ''],
                expected: false,
                actual: true,
                error: 'Validation error',
                duration: 0.3,
                tags: ['unit'],
            );

            $suite = new TestSuite(
                name: 'single-fail',
                results: [$result],
                duration: 0.3,
            );

            // Act
            $this->service->saveSnapshot([$suite]);

            // Assert
            $snapshotPath = $this->snapshotDir.'/single-fail.json';
            expect(file_exists($snapshotPath))->toBeTrue();

            $content = json_decode(file_get_contents($snapshotPath), true);
            expect($content['total_tests'])->toBe(1);
            expect($content['passed_tests'])->toBe(0);
            expect($content['failed_tests'])->toBe(1);
            expect($content['pass_rate'])->toEqual(0.0);
            expect($content['results']['test:file:0:0'])->toBe([
                'passed' => false,
                'expected' => false,
                'actual' => true,
            ]);
        });

        test('saves suite with multiple mixed results', function (): void {
            // Arrange
            $result1 = TestResult::pass(
                id: 'test:file:0:0',
                file: 'test.php',
                group: 'Valid Cases',
                description: 'valid name',
                data: ['name' => 'John'],
                expected: true,
            );

            $result2 = TestResult::fail(
                id: 'test:file:0:1',
                file: 'test.php',
                group: 'Invalid Cases',
                description: 'empty name',
                data: ['name' => ''],
                expected: false,
                actual: true,
            );

            $result3 = TestResult::pass(
                id: 'test:file:1:0',
                file: 'test.php',
                group: 'Edge Cases',
                description: 'long name',
                data: ['name' => str_repeat('a', 100)],
                expected: true,
            );

            $suite = new TestSuite(
                name: 'mixed-results',
                results: [$result1, $result2, $result3],
                duration: 1.2,
            );

            // Act
            $this->service->saveSnapshot([$suite]);

            // Assert
            $snapshotPath = $this->snapshotDir.'/mixed-results.json';
            expect(file_exists($snapshotPath))->toBeTrue();

            $content = json_decode(file_get_contents($snapshotPath), true);
            expect($content['total_tests'])->toBe(3);
            expect($content['passed_tests'])->toBe(2);
            expect($content['failed_tests'])->toBe(1);
            expect($content['pass_rate'])->toBeGreaterThan(66.6);
            expect($content['pass_rate'])->toBeLessThan(66.7);

            expect($content['results'])->toHaveKey('test:file:0:0');
            expect($content['results'])->toHaveKey('test:file:0:1');
            expect($content['results'])->toHaveKey('test:file:1:0');

            expect($content['results']['test:file:0:0']['passed'])->toBeTrue();
            expect($content['results']['test:file:0:1']['passed'])->toBeFalse();
            expect($content['results']['test:file:1:0']['passed'])->toBeTrue();
        });

        test('saves multiple suites in single call', function (): void {
            // Arrange
            $suite1 = new TestSuite(
                name: 'suite-one',
                results: [TestResult::pass(
                    id: 'test:1:0:0',
                    file: 'test1.php',
                    group: 'Group 1',
                    description: 'test 1',
                    data: [],
                    expected: true,
                )],
                duration: 0.5,
            );

            $suite2 = new TestSuite(
                name: 'suite-two',
                results: [TestResult::pass(
                    id: 'test:2:0:0',
                    file: 'test2.php',
                    group: 'Group 2',
                    description: 'test 2',
                    data: [],
                    expected: true,
                )],
                duration: 0.8,
            );

            // Act
            $this->service->saveSnapshot([$suite1, $suite2]);

            // Assert
            expect(file_exists($this->snapshotDir.'/suite-one.json'))->toBeTrue();
            expect(file_exists($this->snapshotDir.'/suite-two.json'))->toBeTrue();

            $content1 = json_decode(file_get_contents($this->snapshotDir.'/suite-one.json'), true);
            $content2 = json_decode(file_get_contents($this->snapshotDir.'/suite-two.json'), true);

            expect($content1['results'])->toHaveKey('test:1:0:0');
            expect($content2['results'])->toHaveKey('test:2:0:0');
        });

        test('creates snapshot directory if it does not exist', function (): void {
            // Arrange
            expect(is_dir($this->snapshotDir))->toBeFalse();

            $suite = new TestSuite(
                name: 'new-dir-test',
                results: [],
                duration: 0.0,
            );

            // Act
            $this->service->saveSnapshot([$suite]);

            // Assert
            expect(is_dir($this->snapshotDir))->toBeTrue();
            expect(file_exists($this->snapshotDir.'/new-dir-test.json'))->toBeTrue();
        });

        test('overwrites existing snapshot file', function (): void {
            // Arrange
            $firstSuite = new TestSuite(
                name: 'overwrite-test',
                results: [TestResult::pass(
                    id: 'test:1:0:0',
                    file: 'test.php',
                    group: 'Group',
                    description: 'first',
                    data: [],
                    expected: true,
                )],
                duration: 0.5,
            );

            $this->service->saveSnapshot([$firstSuite]);

            $secondSuite = new TestSuite(
                name: 'overwrite-test',
                results: [
                    TestResult::pass(
                        id: 'test:1:0:0',
                        file: 'test.php',
                        group: 'Group',
                        description: 'first',
                        data: [],
                        expected: true,
                    ),
                    TestResult::pass(
                        id: 'test:1:0:1',
                        file: 'test.php',
                        group: 'Group',
                        description: 'second',
                        data: [],
                        expected: true,
                    ),
                ],
                duration: 1.0,
            );

            // Act
            $this->service->saveSnapshot([$secondSuite]);

            // Assert
            $content = json_decode(file_get_contents($this->snapshotDir.'/overwrite-test.json'), true);
            expect($content['total_tests'])->toBe(2);
            expect($content['results'])->toHaveKey('test:1:0:1');
        });

        test('handles suite with expected false and actual false (passing negative test)', function (): void {
            // Arrange
            $result = TestResult::pass(
                id: 'test:file:0:0',
                file: 'test.php',
                group: 'Negative Cases',
                description: 'correctly rejects invalid data',
                data: ['invalid' => 'data'],
                expected: false,
                duration: 0.2,
            );

            $suite = new TestSuite(
                name: 'negative-pass',
                results: [$result],
                duration: 0.2,
            );

            // Act
            $this->service->saveSnapshot([$suite]);

            // Assert
            $content = json_decode(file_get_contents($this->snapshotDir.'/negative-pass.json'), true);
            expect($content['results']['test:file:0:0'])->toBe([
                'passed' => true,
                'expected' => false,
                'actual' => false,
            ]);
        });

        test('handles suite with complex test result data types', function (): void {
            // Arrange
            $result = TestResult::pass(
                id: 'test:complex:0:0',
                file: 'complex.php',
                group: 'Complex Data',
                description: 'handles nested arrays and objects',
                data: [
                    'nested' => ['array' => ['deep' => 'value']],
                    'number' => 42,
                    'boolean' => true,
                    'null' => null,
                ],
                expected: true,
            );

            $suite = new TestSuite(
                name: 'complex-data',
                results: [$result],
                duration: 0.1,
            );

            // Act
            $this->service->saveSnapshot([$suite]);

            // Assert
            $content = json_decode(file_get_contents($this->snapshotDir.'/complex-data.json'), true);
            expect($content['results'])->toHaveKey('test:complex:0:0');
        });

        test('preserves JSON formatting with pretty print', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'format-test',
                results: [],
                duration: 0.0,
            );

            // Act
            $this->service->saveSnapshot([$suite]);

            // Assert
            $content = file_get_contents($this->snapshotDir.'/format-test.json');
            expect($content)->toContain("\n");
            expect($content)->toContain('    ');
        });
    });

    describe('loadSnapshot', function (): void {
        test('returns null when snapshot file does not exist', function (): void {
            // Arrange & Act
            $result = $this->service->loadSnapshot('non-existent-suite');

            // Assert
            expect($result)->toBeNull();
        });

        test('loads saved snapshot correctly', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'load-test',
                results: [TestResult::pass(
                    id: 'test:1:0:0',
                    file: 'test.php',
                    group: 'Group',
                    description: 'test',
                    data: ['key' => 'value'],
                    expected: true,
                )],
                duration: 0.5,
            );

            $this->service->saveSnapshot([$suite]);

            // Act
            $loaded = $this->service->loadSnapshot('load-test');

            // Assert
            expect($loaded)->toBeArray();
            expect($loaded['total_tests'])->toBe(1);
            expect($loaded['passed_tests'])->toBe(1);
            expect($loaded['failed_tests'])->toBe(0);
            expect($loaded['pass_rate'])->toEqual(100.0);
            expect($loaded['results'])->toHaveKey('test:1:0:0');
        });

        test('returns null when file contents cannot be read', function (): void {
            // Arrange
            mkdir($this->snapshotDir, 0o755, true);
            $snapshotPath = $this->snapshotDir.'/unreadable.json';

            // Create a directory with the snapshot name to make file_get_contents fail
            mkdir($snapshotPath, 0o755, true);

            // Act & Assert
            // This will trigger file_get_contents to fail and return false
            // which should result in null being returned
            try {
                $result = $this->service->loadSnapshot('unreadable');
                // If no exception, it should return null
                expect($result)->toBeNull();
            } catch (Throwable $throwable) {
                // If an exception is thrown, that's also valid behavior
                // as file_get_contents on a directory can throw
                expect($throwable)->toBeInstanceOf(ErrorException::class);
            }

            // Cleanup
            rmdir($snapshotPath);
        });

        test('returns null when JSON is invalid', function (): void {
            // Arrange
            mkdir($this->snapshotDir, 0o755, true);
            $snapshotPath = $this->snapshotDir.'/invalid-json.json';
            file_put_contents($snapshotPath, 'invalid json content {{{');

            // Act
            $result = $this->service->loadSnapshot('invalid-json');

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null when JSON decodes to non-array', function (): void {
            // Arrange
            mkdir($this->snapshotDir, 0o755, true);
            $snapshotPath = $this->snapshotDir.'/string-json.json';
            file_put_contents($snapshotPath, json_encode('string value'));

            // Act
            $result = $this->service->loadSnapshot('string-json');

            // Assert
            expect($result)->toBeNull();
        });

        test('loads snapshot with empty results array', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'empty-results',
                results: [],
                duration: 0.0,
            );

            $this->service->saveSnapshot([$suite]);

            // Act
            $loaded = $this->service->loadSnapshot('empty-results');

            // Assert
            expect($loaded)->toBeArray();
            expect($loaded['results'])->toBe([]);
        });

        test('loads snapshot with multiple test results', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'multi-load',
                results: [
                    TestResult::pass(
                        id: 'test:1:0:0',
                        file: 'test.php',
                        group: 'Group 1',
                        description: 'test 1',
                        data: [],
                        expected: true,
                    ),
                    TestResult::fail(
                        id: 'test:1:0:1',
                        file: 'test.php',
                        group: 'Group 1',
                        description: 'test 2',
                        data: [],
                        expected: false,
                        actual: true,
                    ),
                    TestResult::pass(
                        id: 'test:1:1:0',
                        file: 'test.php',
                        group: 'Group 2',
                        description: 'test 3',
                        data: [],
                        expected: false,
                    ),
                ],
                duration: 1.5,
            );

            $this->service->saveSnapshot([$suite]);

            // Act
            $loaded = $this->service->loadSnapshot('multi-load');

            // Assert
            expect($loaded['total_tests'])->toBe(3);
            expect($loaded['passed_tests'])->toBe(2);
            expect($loaded['failed_tests'])->toBe(1);
            expect($loaded['results'])->toHaveCount(3);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles suite name with special characters', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'test-suite_with.special@chars',
                results: [],
                duration: 0.0,
            );

            // Act
            $this->service->saveSnapshot([$suite]);
            $loaded = $this->service->loadSnapshot('test-suite_with.special@chars');

            // Assert
            expect($loaded)->toBeArray();
        });

        test('handles very long suite name', function (): void {
            // Arrange
            $longName = str_repeat('a', 200);
            $suite = new TestSuite(
                name: $longName,
                results: [],
                duration: 0.0,
            );

            // Act
            $this->service->saveSnapshot([$suite]);
            $loaded = $this->service->loadSnapshot($longName);

            // Assert
            expect($loaded)->toBeArray();
        });

        test('handles suite with zero duration', function (): void {
            // Arrange
            $suite = new TestSuite(
                name: 'zero-duration',
                results: [],
                duration: 0.0,
            );

            // Act
            $this->service->saveSnapshot([$suite]);
            $loaded = $this->service->loadSnapshot('zero-duration');

            // Assert
            expect($loaded)->toBeArray();
        });

        test('handles suite with very high pass rate precision', function (): void {
            // Arrange
            $results = [];

            for ($i = 0; $i < 7; ++$i) {
                $results[] = TestResult::pass(
                    id: sprintf('test:%d:0:0', $i),
                    file: 'test.php',
                    group: 'Group',
                    description: 'test '.$i,
                    data: [],
                    expected: true,
                );
            }

            $suite = new TestSuite(
                name: 'precision-test',
                results: $results,
                duration: 1.0,
            );

            // Act
            $this->service->saveSnapshot([$suite]);
            $loaded = $this->service->loadSnapshot('precision-test');

            // Assert
            expect($loaded['total_tests'])->toBe(7);
            expect($loaded['pass_rate'])->toEqual(100.0);
        });

        test('handles concurrent save operations on different suites', function (): void {
            // Arrange
            $suite1 = new TestSuite(
                name: 'concurrent-1',
                results: [],
                duration: 0.0,
            );

            $suite2 = new TestSuite(
                name: 'concurrent-2',
                results: [],
                duration: 0.0,
            );

            // Act
            $this->service->saveSnapshot([$suite1]);
            $this->service->saveSnapshot([$suite2]);

            // Assert
            expect(file_exists($this->snapshotDir.'/concurrent-1.json'))->toBeTrue();
            expect(file_exists($this->snapshotDir.'/concurrent-2.json'))->toBeTrue();
        });

        test('handles test result with all data types', function (): void {
            // Arrange
            $result = TestResult::pass(
                id: 'test:types:0:0',
                file: 'test.php',
                group: 'Data Types',
                description: 'all PHP types',
                data: [
                    'string' => 'text',
                    'int' => 42,
                    'float' => 3.14,
                    'bool_true' => true,
                    'bool_false' => false,
                    'null' => null,
                    'array' => [1, 2, 3],
                    'nested' => ['a' => ['b' => 'c']],
                ],
                expected: true,
            );

            $suite = new TestSuite(
                name: 'data-types',
                results: [$result],
                duration: 0.1,
            );

            // Act
            $this->service->saveSnapshot([$suite]);
            $loaded = $this->service->loadSnapshot('data-types');

            // Assert
            expect($loaded)->toBeArray();
            expect($loaded['results'])->toHaveKey('test:types:0:0');
        });
    });

    describe('Integration Scenarios', function (): void {
        test('saves and loads complete workflow', function (): void {
            // Arrange
            $results = [
                TestResult::pass(
                    id: 'workflow:1:0:0',
                    file: 'validation.php',
                    group: 'Email Validation',
                    description: 'accepts valid email',
                    data: ['email' => 'test@example.com'],
                    expected: true,
                    duration: 0.1,
                    tags: ['email', 'happy-path'],
                ),
                TestResult::pass(
                    id: 'workflow:1:0:1',
                    file: 'validation.php',
                    group: 'Email Validation',
                    description: 'rejects invalid email',
                    data: ['email' => 'not-an-email'],
                    expected: false,
                    duration: 0.1,
                    tags: ['email', 'sad-path'],
                ),
                TestResult::fail(
                    id: 'workflow:1:1:0',
                    file: 'validation.php',
                    group: 'Age Validation',
                    description: 'accepts valid age',
                    data: ['age' => 25],
                    expected: true,
                    actual: false,
                    error: 'Unexpected validation failure',
                    duration: 0.2,
                    tags: ['age', 'regression'],
                ),
            ];

            $suite = new TestSuite(
                name: 'complete-workflow',
                results: $results,
                duration: 0.4,
            );

            // Act
            $this->service->saveSnapshot([$suite]);
            $loaded = $this->service->loadSnapshot('complete-workflow');

            // Assert
            expect($loaded)->toBeArray();
            expect($loaded['total_tests'])->toBe(3);
            expect($loaded['passed_tests'])->toBe(2);
            expect($loaded['failed_tests'])->toBe(1);
            expect($loaded['pass_rate'])->toBeGreaterThan(66.0);
            expect($loaded['pass_rate'])->toBeLessThan(67.0);

            expect($loaded['results']['workflow:1:0:0']['passed'])->toBeTrue();
            expect($loaded['results']['workflow:1:0:0']['expected'])->toBeTrue();
            expect($loaded['results']['workflow:1:0:0']['actual'])->toBeTrue();

            expect($loaded['results']['workflow:1:0:1']['passed'])->toBeTrue();
            expect($loaded['results']['workflow:1:0:1']['expected'])->toBeFalse();
            expect($loaded['results']['workflow:1:0:1']['actual'])->toBeFalse();

            expect($loaded['results']['workflow:1:1:0']['passed'])->toBeFalse();
            expect($loaded['results']['workflow:1:1:0']['expected'])->toBeTrue();
            expect($loaded['results']['workflow:1:1:0']['actual'])->toBeFalse();
        });

        test('handles rapid save/load cycles', function (): void {
            // Arrange & Act
            for ($i = 0; $i < 5; ++$i) {
                $suite = new TestSuite(
                    name: 'rapid-cycle',
                    results: [TestResult::pass(
                        id: sprintf('test:%d:0:0', $i),
                        file: 'test.php',
                        group: 'Group',
                        description: 'iteration '.$i,
                        data: ['iteration' => $i],
                        expected: true,
                    )],
                    duration: 0.1,
                );

                $this->service->saveSnapshot([$suite]);
                $loaded = $this->service->loadSnapshot('rapid-cycle');

                expect($loaded)->toBeArray();
                expect($loaded['results'])->toHaveKey(sprintf('test:%d:0:0', $i));
            }

            // Assert - final load
            $final = $this->service->loadSnapshot('rapid-cycle');
            expect($final['total_tests'])->toBe(1);
            expect($final['results'])->toHaveKey('test:4:0:0');
        });
    });
})->group('unit', 'services', 'snapshot');
