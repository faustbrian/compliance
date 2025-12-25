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

        expect($output->fetch())->toContain('ALL TESTS PASSED');
    });

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
});
