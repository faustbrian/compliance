<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Services\CoverageService;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;

describe('CoverageService', function (): void {
    beforeEach(function (): void {
        $this->service = new CoverageService();
    });

    describe('empty suites', function (): void {
        test('analyzes empty test suite array and returns zero metrics', function (): void {
            $result = $this->service->analyze([]);

            expect($result['total_tests'])->toBe(0)
                ->and($result['passed_tests'])->toBe(0)
                ->and($result['failed_tests'])->toBe(0)
                ->and($result['pass_rate'])->toBe(0.0)
                ->and($result['groups']['count'])->toBe(0)
                ->and($result['groups']['distribution'])->toBe([])
                ->and($result['files']['count'])->toBe(0)
                ->and($result['files']['distribution'])->toBe([])
                ->and($result['tags']['count'])->toBe(0)
                ->and($result['tags']['distribution'])->toBe([])
                ->and($result['coverage_score'])->toBe(0.0);
        });

        test('analyzes suite with zero tests and returns zero pass rate', function (): void {
            $suite = new TestSuite(
                name: 'Empty Suite',
                results: [],
                duration: 0.0,
            );

            $result = $this->service->analyze([$suite]);

            expect($result['total_tests'])->toBe(0)
                ->and($result['passed_tests'])->toBe(0)
                ->and($result['failed_tests'])->toBe(0)
                ->and($result['pass_rate'])->toBe(0.0);
        });

        test('calculates coverage score of zero for empty suites', function (): void {
            $result = $this->service->analyze([]);

            expect($result['coverage_score'])->toBe(0.0);
        });

        test('handles suite with zero tests when calculating coverage score', function (): void {
            $suite = new TestSuite('Empty Suite', [], 0.0);

            $result = $this->service->analyze([$suite]);

            expect($result['coverage_score'])->toBe(0.0);
        });

        test('calculates accurate pass rate with division by zero protection', function (): void {
            $suite = new TestSuite('Empty Suite', [], 0.0);

            $result = $this->service->analyze([$suite]);

            expect($result['pass_rate'])->toBe(0.0);
        });
    });

    describe('single suite analysis', function (): void {
        test('analyzes single suite with passing test', function (): void {
            $testResult = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'tests/example.php',
                group: 'Authentication',
                description: 'validates user login',
                data: ['username' => 'test'],
                expected: true,
                duration: 0.5,
                tags: ['security', 'auth'],
            );

            $suite = new TestSuite(
                name: 'Auth Suite',
                results: [$testResult],
                duration: 0.5,
            );

            $result = $this->service->analyze([$suite]);

            expect($result['total_tests'])->toBe(1)
                ->and($result['passed_tests'])->toBe(1)
                ->and($result['failed_tests'])->toBe(0)
                ->and($result['pass_rate'])->toEqual(100.0)
                ->and($result['groups']['count'])->toBe(1)
                ->and($result['groups']['distribution'])->toBe(['Authentication' => 1])
                ->and($result['files']['count'])->toBe(1)
                ->and($result['files']['distribution'])->toBe(['tests/example.php' => 1])
                ->and($result['tags']['count'])->toBe(2)
                ->and($result['tags']['distribution'])->toBe(['security' => 1, 'auth' => 1]);
        });

        test('analyzes single suite with failing test', function (): void {
            $testResult = TestResult::fail(
                id: 'suite:file:0:0',
                file: 'tests/validation.php',
                group: 'Validation',
                description: 'rejects invalid email',
                data: ['email' => 'invalid'],
                expected: false,
                actual: true,
                error: 'Expected validation to fail',
                duration: 0.3,
                tags: ['validation'],
            );

            $suite = new TestSuite(
                name: 'Validation Suite',
                results: [$testResult],
                duration: 0.3,
            );

            $result = $this->service->analyze([$suite]);

            expect($result['total_tests'])->toBe(1)
                ->and($result['passed_tests'])->toBe(0)
                ->and($result['failed_tests'])->toBe(1)
                ->and($result['pass_rate'])->toEqual(0.0);
        });

        test('analyzes suite with mixed passing and failing tests', function (): void {
            $passingTest = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'tests/auth.php',
                group: 'Auth',
                description: 'validates credentials',
                data: ['user' => 'admin'],
                expected: true,
                tags: ['auth'],
            );

            $failingTest = TestResult::fail(
                id: 'suite:file:0:1',
                file: 'tests/auth.php',
                group: 'Auth',
                description: 'rejects bad credentials',
                data: ['user' => 'hacker'],
                expected: false,
                actual: true,
                error: 'Validation passed unexpectedly',
                tags: ['auth'],
            );

            $suite = new TestSuite(
                name: 'Mixed Suite',
                results: [$passingTest, $failingTest],
                duration: 1.0,
            );

            $result = $this->service->analyze([$suite]);

            expect($result['total_tests'])->toBe(2)
                ->and($result['passed_tests'])->toBe(1)
                ->and($result['failed_tests'])->toBe(1)
                ->and($result['pass_rate'])->toEqual(50.0);
        });
    });

    describe('multiple suites analysis', function (): void {
        test('analyzes multiple test suites and aggregates metrics', function (): void {
            $suite1Result = TestResult::pass(
                id: 'suite1:file:0:0',
                file: 'tests/suite1.php',
                group: 'Group1',
                description: 'test 1',
                data: [],
                expected: true,
                tags: ['tag1'],
            );

            $suite2Result1 = TestResult::pass(
                id: 'suite2:file:0:0',
                file: 'tests/suite2.php',
                group: 'Group2',
                description: 'test 2',
                data: [],
                expected: true,
                tags: ['tag2'],
            );

            $suite2Result2 = TestResult::fail(
                id: 'suite2:file:0:1',
                file: 'tests/suite2.php',
                group: 'Group2',
                description: 'test 3',
                data: [],
                expected: false,
                actual: true,
                tags: ['tag2'],
            );

            $suite1 = new TestSuite('Suite 1', [$suite1Result], 0.5);
            $suite2 = new TestSuite('Suite 2', [$suite2Result1, $suite2Result2], 1.0);

            $result = $this->service->analyze([$suite1, $suite2]);

            expect($result['total_tests'])->toBe(3)
                ->and($result['passed_tests'])->toBe(2)
                ->and($result['failed_tests'])->toBe(1)
                ->and($result['pass_rate'])->toEqualWithDelta(66.67, 0.01)
                ->and($result['groups']['count'])->toBe(2)
                ->and($result['files']['count'])->toBe(2)
                ->and($result['tags']['count'])->toBe(2);
        });

        test('preserves unique group counts across multiple suites', function (): void {
            $suite1Result = TestResult::pass(
                id: 'suite1:file:0:0',
                file: 'test1.php',
                group: 'SharedGroup',
                description: 'test 1',
                data: [],
                expected: true,
            );

            $suite2Result = TestResult::pass(
                id: 'suite2:file:0:0',
                file: 'test2.php',
                group: 'SharedGroup',
                description: 'test 2',
                data: [],
                expected: true,
            );

            $suite1 = new TestSuite('Suite 1', [$suite1Result], 0.5);
            $suite2 = new TestSuite('Suite 2', [$suite2Result], 0.5);

            $result = $this->service->analyze([$suite1, $suite2]);

            // Both tests share the same group, so count should be 1 unique group
            expect($result['groups']['count'])->toBe(1)
                ->and($result['groups']['distribution'])->toBe(['SharedGroup' => 2]);
        });
    });

    describe('distribution tracking', function (): void {
        test('tracks group distribution correctly across multiple tests', function (): void {
            $result1 = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'GroupA',
                description: 'test 1',
                data: [],
                expected: true,
            );

            $result2 = TestResult::pass(
                id: 'suite:file:0:1',
                file: 'test.php',
                group: 'GroupA',
                description: 'test 2',
                data: [],
                expected: true,
            );

            $result3 = TestResult::pass(
                id: 'suite:file:0:2',
                file: 'test.php',
                group: 'GroupB',
                description: 'test 3',
                data: [],
                expected: true,
            );

            $suite = new TestSuite('Suite', [$result1, $result2, $result3], 1.0);

            $result = $this->service->analyze([$suite]);

            expect($result['groups']['count'])->toBe(2)
                ->and($result['groups']['distribution'])->toBe(['GroupA' => 2, 'GroupB' => 1]);
        });

        test('tracks file distribution correctly across multiple tests', function (): void {
            $result1 = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'tests/file1.php',
                group: 'Group',
                description: 'test 1',
                data: [],
                expected: true,
            );

            $result2 = TestResult::pass(
                id: 'suite:file:0:1',
                file: 'tests/file1.php',
                group: 'Group',
                description: 'test 2',
                data: [],
                expected: true,
            );

            $result3 = TestResult::pass(
                id: 'suite:file:0:2',
                file: 'tests/file2.php',
                group: 'Group',
                description: 'test 3',
                data: [],
                expected: true,
            );

            $suite = new TestSuite('Suite', [$result1, $result2, $result3], 1.0);

            $result = $this->service->analyze([$suite]);

            expect($result['files']['count'])->toBe(2)
                ->and($result['files']['distribution'])->toBe(['tests/file1.php' => 2, 'tests/file2.php' => 1]);
        });

        test('tracks tag distribution correctly with multiple tags per test', function (): void {
            $result1 = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test 1',
                data: [],
                expected: true,
                tags: ['tag1', 'tag2'],
            );

            $result2 = TestResult::pass(
                id: 'suite:file:0:1',
                file: 'test.php',
                group: 'Group',
                description: 'test 2',
                data: [],
                expected: true,
                tags: ['tag1', 'tag3'],
            );

            $result3 = TestResult::pass(
                id: 'suite:file:0:2',
                file: 'test.php',
                group: 'Group',
                description: 'test 3',
                data: [],
                expected: true,
                tags: ['tag3'],
            );

            $suite = new TestSuite('Suite', [$result1, $result2, $result3], 1.0);

            $result = $this->service->analyze([$suite]);

            expect($result['tags']['count'])->toBe(3)
                ->and($result['tags']['distribution'])->toBe(['tag1' => 2, 'tag3' => 2, 'tag2' => 1]);
        });

        test('handles tests with no tags correctly', function (): void {
            $testResult = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test without tags',
                data: [],
                expected: true,
                tags: [],
            );

            $suite = new TestSuite('Suite', [$testResult], 1.0);

            $result = $this->service->analyze([$suite]);

            expect($result['tags']['count'])->toBe(0)
                ->and($result['tags']['distribution'])->toBe([]);
        });

        test('sorts distribution by count in descending order', function (): void {
            $result1 = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'GroupA',
                description: 'test 1',
                data: [],
                expected: true,
            );

            $result2 = TestResult::pass(
                id: 'suite:file:0:1',
                file: 'test.php',
                group: 'GroupB',
                description: 'test 2',
                data: [],
                expected: true,
            );

            $result3 = TestResult::pass(
                id: 'suite:file:0:2',
                file: 'test.php',
                group: 'GroupB',
                description: 'test 3',
                data: [],
                expected: true,
            );

            $result4 = TestResult::pass(
                id: 'suite:file:0:3',
                file: 'test.php',
                group: 'GroupC',
                description: 'test 4',
                data: [],
                expected: true,
            );

            $result5 = TestResult::pass(
                id: 'suite:file:0:4',
                file: 'test.php',
                group: 'GroupC',
                description: 'test 5',
                data: [],
                expected: true,
            );

            $result6 = TestResult::pass(
                id: 'suite:file:0:5',
                file: 'test.php',
                group: 'GroupC',
                description: 'test 6',
                data: [],
                expected: true,
            );

            $suite = new TestSuite('Suite', [$result1, $result2, $result3, $result4, $result5, $result6], 1.0);

            $result = $this->service->analyze([$suite]);

            $distribution = $result['groups']['distribution'];
            $keys = array_keys($distribution);

            expect($keys[0])->toBe('GroupC') // 3 tests
                ->and($keys[1])->toBe('GroupB') // 2 tests
                ->and($keys[2])->toBe('GroupA') // 1 test
                ->and($distribution['GroupC'])->toBe(3)
                ->and($distribution['GroupB'])->toBe(2)
                ->and($distribution['GroupA'])->toBe(1);
        });

        test('handles identical group and file names correctly', function (): void {
            $result1 = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'duplicate.php',
                group: 'duplicate',
                description: 'test 1',
                data: [],
                expected: true,
            );

            $result2 = TestResult::pass(
                id: 'suite:file:0:1',
                file: 'duplicate.php',
                group: 'duplicate',
                description: 'test 2',
                data: [],
                expected: true,
            );

            $suite = new TestSuite('Suite', [$result1, $result2], 1.0);

            $result = $this->service->analyze([$suite]);

            expect($result['groups']['count'])->toBe(1)
                ->and($result['groups']['distribution'])->toBe(['duplicate' => 2])
                ->and($result['files']['count'])->toBe(1)
                ->and($result['files']['distribution'])->toBe(['duplicate.php' => 2]);
        });
    });

    describe('coverage score calculation', function (): void {
        test('calculates coverage score with 100% pass rate', function (): void {
            $result1 = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group1',
                description: 'test 1',
                data: [],
                expected: true,
            );

            $result2 = TestResult::pass(
                id: 'suite:file:0:1',
                file: 'test.php',
                group: 'Group2',
                description: 'test 2',
                data: [],
                expected: true,
            );

            $suite = new TestSuite('Suite', [$result1, $result2], 1.0);

            $result = $this->service->analyze([$suite]);

            // Score = (1.0 * 0.6) + ((2/10) * 0.2) + ((1/10) * 0.2) * 100
            // Score = (0.6 + 0.04 + 0.02) * 100 = 66.0
            expect($result['coverage_score'])->toEqualWithDelta(66.0, 0.01);
        });

        test('calculates coverage score with 0% pass rate', function (): void {
            $result1 = TestResult::fail(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group1',
                description: 'test 1',
                data: [],
                expected: false,
                actual: true,
            );

            $suite = new TestSuite('Suite', [$result1], 1.0);

            $result = $this->service->analyze([$suite]);

            // Score = (0.0 * 0.6) + ((1/10) * 0.2) + ((1/10) * 0.2) * 100
            // Score = (0.0 + 0.02 + 0.02) * 100 = 4.0
            expect($result['coverage_score'])->toEqualWithDelta(4.0, 0.01);
        });

        test('calculates coverage score with high group diversity', function (): void {
            $results = [];

            for ($i = 0; $i < 10; ++$i) {
                $results[] = TestResult::pass(
                    id: 'suite:file:0:'.$i,
                    file: 'test.php',
                    group: 'Group'.$i,
                    description: 'test '.$i,
                    data: [],
                    expected: true,
                );
            }

            $suite = new TestSuite('Suite', $results, 1.0);

            $result = $this->service->analyze([$suite]);

            // Score = (1.0 * 0.6) + ((10/10) * 0.2) + ((1/10) * 0.2) * 100
            // Score = (0.6 + 0.2 + 0.02) * 100 = 82.0
            expect($result['coverage_score'])->toEqualWithDelta(82.0, 0.01);
        });

        test('calculates coverage score with high file diversity', function (): void {
            $results = [];

            for ($i = 0; $i < 10; ++$i) {
                $results[] = TestResult::pass(
                    id: 'suite:file:0:'.$i,
                    file: sprintf('tests/file%d.php', $i),
                    group: 'Group',
                    description: 'test '.$i,
                    data: [],
                    expected: true,
                );
            }

            $suite = new TestSuite('Suite', $results, 1.0);

            $result = $this->service->analyze([$suite]);

            // Score = (1.0 * 0.6) + ((1/10) * 0.2) + ((10/10) * 0.2) * 100
            // Score = (0.6 + 0.02 + 0.2) * 100 = 82.0
            expect($result['coverage_score'])->toEqualWithDelta(82.0, 0.01);
        });

        test('calculates coverage score capped at 100%', function (): void {
            $results = [];

            // Create 50 unique groups and 50 unique files to exceed the scoring cap
            for ($i = 0; $i < 50; ++$i) {
                $results[] = TestResult::pass(
                    id: 'suite:file:0:'.$i,
                    file: sprintf('tests/file%d.php', $i),
                    group: 'Group'.$i,
                    description: 'test '.$i,
                    data: [],
                    expected: true,
                );
            }

            $suite = new TestSuite('Suite', $results, 1.0);

            $result = $this->service->analyze([$suite]);

            expect($result['coverage_score'])->toBeLessThanOrEqual(100.0)
                ->and($result['coverage_score'])->toBe(100.0);
        });

        test('calculates coverage score with mixed pass rate and diversity', function (): void {
            $pass1 = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'tests/file1.php',
                group: 'Group1',
                description: 'test 1',
                data: [],
                expected: true,
            );

            $pass2 = TestResult::pass(
                id: 'suite:file:0:1',
                file: 'tests/file2.php',
                group: 'Group2',
                description: 'test 2',
                data: [],
                expected: true,
            );

            $fail1 = TestResult::fail(
                id: 'suite:file:0:2',
                file: 'tests/file3.php',
                group: 'Group3',
                description: 'test 3',
                data: [],
                expected: false,
                actual: true,
            );

            $fail2 = TestResult::fail(
                id: 'suite:file:0:3',
                file: 'tests/file4.php',
                group: 'Group4',
                description: 'test 4',
                data: [],
                expected: false,
                actual: true,
            );

            $suite = new TestSuite('Suite', [$pass1, $pass2, $fail1, $fail2], 1.0);

            $result = $this->service->analyze([$suite]);

            // Pass rate = 2/4 = 0.5
            // Group diversity = 4
            // File diversity = 4
            // Score = (0.5 * 0.6) + ((4/10) * 0.2) + ((4/10) * 0.2) * 100
            // Score = (0.3 + 0.08 + 0.08) * 100 = 46.0
            expect($result['coverage_score'])->toEqualWithDelta(46.0, 0.01);
        });
    });

    describe('result structure', function (): void {
        test('returns complete result structure with all required keys', function (): void {
            $testResult = TestResult::pass(
                id: 'suite:file:0:0',
                file: 'test.php',
                group: 'Group',
                description: 'test',
                data: [],
                expected: true,
            );

            $suite = new TestSuite('Suite', [$testResult], 1.0);

            $result = $this->service->analyze([$suite]);

            expect($result)->toHaveKeys([
                'total_tests',
                'passed_tests',
                'failed_tests',
                'pass_rate',
                'groups',
                'files',
                'tags',
                'coverage_score',
            ])
                ->and($result['groups'])->toHaveKeys(['count', 'distribution'])
                ->and($result['files'])->toHaveKeys(['count', 'distribution'])
                ->and($result['tags'])->toHaveKeys(['count', 'distribution']);
        });
    });
});
