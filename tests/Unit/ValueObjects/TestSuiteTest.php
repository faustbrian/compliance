<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Compliance\ValueObjects\TestResult;
use Cline\Compliance\ValueObjects\TestSuite;

describe('TestSuite Value Object', function (): void {
    test('calculates statistics for all passing tests', function (): void {
        $results = [
            TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
            TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
            TestResult::pass('t3', 'f1', 'g1', 'd3', [], true),
        ];

        $suite = new TestSuite(
            name: 'Test Suite',
            results: $results,
            duration: 1.5,
        );

        expect($suite->totalTests())->toBe(3)
            ->and($suite->passedTests())->toBe(3)
            ->and($suite->failedTests())->toBe(0)
            ->and($suite->passRate())->toBe(100.0)
            ->and($suite->duration)->toBe(1.5);
    });

    test('calculates statistics for mixed results', function (): void {
        $results = [
            TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
            TestResult::fail('t2', 'f1', 'g1', 'd2', [], true, false),
            TestResult::pass('t3', 'f1', 'g1', 'd3', [], true),
            TestResult::fail('t4', 'f1', 'g1', 'd4', [], false, true),
        ];

        $suite = new TestSuite(
            name: 'Mixed Suite',
            results: $results,
            duration: 2.3,
        );

        expect($suite->totalTests())->toBe(4)
            ->and($suite->passedTests())->toBe(2)
            ->and($suite->failedTests())->toBe(2)
            ->and($suite->passRate())->toBe(50.0);
    });

    test('calculates pass rate with precision', function (): void {
        $results = [
            TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
            TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
            TestResult::fail('t3', 'f1', 'g1', 'd3', [], true, false),
        ];

        $suite = new TestSuite(
            name: 'Precision Suite',
            results: $results,
            duration: 0.5,
        );

        expect($suite->passRate())->toBeGreaterThan(66.6)
            ->and($suite->passRate())->toBeLessThan(66.7);
    });

    test('handles empty test suite', function (): void {
        $suite = new TestSuite(
            name: 'Empty Suite',
            results: [],
            duration: 0.0,
        );

        expect($suite->totalTests())->toBe(0)
            ->and($suite->passedTests())->toBe(0)
            ->and($suite->failedTests())->toBe(0)
            ->and($suite->passRate())->toBe(0.0);
    });

    test('provides access to all failed tests', function (): void {
        $pass1 = TestResult::pass('t1', 'f1', 'g1', 'd1', [], true);
        $fail1 = TestResult::fail('t2', 'f1', 'g1', 'd2', [], true, false);
        $pass2 = TestResult::pass('t3', 'f1', 'g1', 'd3', [], true);
        $fail2 = TestResult::fail('t4', 'f1', 'g1', 'd4', [], false, true);

        $suite = new TestSuite(
            name: 'Failed Tests Suite',
            results: [$pass1, $fail1, $pass2, $fail2],
            duration: 1.0,
        );

        $failures = $suite->failures();

        expect($failures)->toHaveCount(2)
            ->and($failures[0])->toBe($fail1)
            ->and($failures[1])->toBe($fail2);
    });
});
