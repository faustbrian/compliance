<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\ValueObjects\TestResult;

describe('TestResult Value Object', function (): void {
    test('creates passing test result', function (): void {
        $result = TestResult::pass(
            id: 'test-1',
            file: 'test.json',
            group: 'Basic Tests',
            description: 'should validate',
            data: ['foo' => 'bar'],
            expectedValid: true,
        );

        expect($result->passed)->toBeTrue()
            ->and($result->id)->toBe('test-1')
            ->and($result->file)->toBe('test.json')
            ->and($result->group)->toBe('Basic Tests')
            ->and($result->description)->toBe('should validate')
            ->and($result->data)->toBe(['foo' => 'bar'])
            ->and($result->expectedValid)->toBeTrue()
            ->and($result->actualValid)->toBeTrue()
            ->and($result->error)->toBeNull();
    });

    test('creates failing test result with actual validity mismatch', function (): void {
        $result = TestResult::fail(
            id: 'test-2',
            file: 'test.json',
            group: 'Validation Tests',
            description: 'should reject invalid data',
            data: ['invalid' => 'data'],
            expectedValid: false,
            actualValid: true,
        );

        expect($result->passed)->toBeFalse()
            ->and($result->expectedValid)->toBeFalse()
            ->and($result->actualValid)->toBeTrue()
            ->and($result->error)->toBeNull();
    });

    test('creates failing test result with error', function (): void {
        $result = TestResult::fail(
            id: 'test-3',
            file: 'test.json',
            group: 'Error Tests',
            description: 'should handle errors',
            data: null,
            expectedValid: true,
            actualValid: false,
            error: 'Validation error occurred',
        );

        expect($result->passed)->toBeFalse()
            ->and($result->error)->toBe('Validation error occurred');
    });
});
