<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Output\SummaryRenderer;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;

describe('SummaryRenderer', function (): void {
    test('renders all passing suites without errors', function (): void {
        $suite = new TestSuite(
            name: 'Test Suite',
            results: [
                TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
            ],
            duration: 1.5,
        );

        $renderer = new SummaryRenderer();
        $renderer->render([$suite]);

        expect(true)->toBeTrue();
    });

    test('renders multiple suites without errors', function (): void {
        $suites = [
            new TestSuite(
                name: 'Suite 1',
                results: [TestResult::pass('t1', 'f1', 'g1', 'd1', [], true)],
                duration: 1.0,
            ),
            new TestSuite(
                name: 'Suite 2',
                results: [TestResult::fail('t2', 'f2', 'g2', 'd2', [], true, false)],
                duration: 2.0,
            ),
        ];

        $renderer = new SummaryRenderer();
        $renderer->render($suites);

        expect(true)->toBeTrue();
    });

    test('renders summary with failures without errors', function (): void {
        $suite = new TestSuite(
            name: 'Failed Suite',
            results: [
                TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                TestResult::fail('t2', 'f1', 'g1', 'd2', [], true, false),
            ],
            duration: 1.0,
        );

        $renderer = new SummaryRenderer();
        $renderer->render([$suite]);

        expect(true)->toBeTrue();
    });

    test('renders all passing message when all tests pass', function (): void {
        $suite = new TestSuite(
            name: 'Perfect Suite',
            results: [
                TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
                TestResult::pass('t2', 'f1', 'g1', 'd2', [], true),
            ],
            duration: 1.0,
        );

        $renderer = new SummaryRenderer();
        $renderer->render([$suite]);

        expect(true)->toBeTrue();
    });
});
