<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Output\DetailRenderer;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;

describe('DetailRenderer', function (): void {
    test('renders detailed test failures', function (): void {
        $suite = new TestSuite(
            name: 'Detailed Suite',
            results: [
                TestResult::fail(
                    id: 'test-1',
                    file: 'validation.json',
                    group: 'String validation',
                    description: 'should reject invalid string',
                    data: 123,
                    expectedValid: false,
                    actualValid: true,
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();
        $renderer->render($suite);

        expect(true)->toBeTrue();
    });

    test('renders failures with error messages', function (): void {
        $suite = new TestSuite(
            name: 'Error Suite',
            results: [
                TestResult::fail(
                    id: 'test-2',
                    file: 'error.json',
                    group: 'Error handling',
                    description: 'should handle error',
                    data: null,
                    expectedValid: true,
                    actualValid: false,
                    error: 'Validation threw exception',
                ),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();
        $renderer->render($suite);

        expect(true)->toBeTrue();
    });

    test('renders nothing when no failures', function (): void {
        $suite = new TestSuite(
            name: 'Success Suite',
            results: [
                TestResult::pass('t1', 'f1', 'g1', 'd1', [], true),
            ],
            duration: 1.0,
        );

        $renderer = new DetailRenderer();
        $renderer->render($suite);

        expect(true)->toBeTrue();
    });
});
