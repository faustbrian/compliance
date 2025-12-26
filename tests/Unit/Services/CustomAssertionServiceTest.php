<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Assertions\AnyOfAssertion;
use Cline\Prism\Assertions\StrictEqualityAssertion;
use Cline\Prism\Services\CustomAssertionService;

describe('CustomAssertionService', function (): void {
    describe('constructor', function (): void {
        test('creates service with empty assertions array', function (): void {
            // Act
            $service = new CustomAssertionService();

            // Assert
            expect($service->getAssertionNames())->toBe([]);
        });

        test('creates service with custom assertions', function (): void {
            // Arrange
            $assertions = [
                'anyOf' => new AnyOfAssertion(),
                'strict' => new StrictEqualityAssertion(),
            ];

            // Act
            $service = new CustomAssertionService($assertions);

            // Assert
            expect($service->getAssertionNames())->toBe(['anyOf', 'strict']);
        });
    });

    describe('execute()', function (): void {
        describe('with null assertion name', function (): void {
            test('uses StrictEqualityAssertion when assertion name is null and assertion passes', function (): void {
                // Arrange
                $service = new CustomAssertionService();
                $data = 'test-data';
                $expected = true;
                $actual = true;

                // Act
                $result = $service->execute(null, $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => true,
                    'message' => null,
                ]);
            });

            test('uses StrictEqualityAssertion when assertion name is null and assertion fails', function (): void {
                // Arrange
                $service = new CustomAssertionService();
                $data = 'test-data';
                $expected = true;
                $actual = false;

                // Act
                $result = $service->execute(null, $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => false,
                    'message' => 'Expected data to be valid, but validator returned invalid',
                ]);
            });
        });

        describe('with missing assertion name', function (): void {
            test('uses StrictEqualityAssertion when assertion name not found and assertion passes', function (): void {
                // Arrange
                $service = new CustomAssertionService([
                    'anyOf' => new AnyOfAssertion(),
                ]);
                $data = 'test-data';
                $expected = false;
                $actual = false;

                // Act
                $result = $service->execute('nonexistent', $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => true,
                    'message' => null,
                ]);
            });

            test('uses StrictEqualityAssertion when assertion name not found and assertion fails', function (): void {
                // Arrange
                $service = new CustomAssertionService([
                    'anyOf' => new AnyOfAssertion(),
                ]);
                $data = 'test-data';
                $expected = false;
                $actual = true;

                // Act
                $result = $service->execute('nonexistent', $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => false,
                    'message' => 'Expected data to be invalid, but validator returned valid',
                ]);
            });
        });

        describe('with registered assertion', function (): void {
            test('executes registered AnyOfAssertion and assertion passes', function (): void {
                // Arrange
                $service = new CustomAssertionService([
                    'anyOf' => new AnyOfAssertion(),
                ]);
                $data = 'test-data';
                $expected = [true, false];
                $actual = true;

                // Act
                $result = $service->execute('anyOf', $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => true,
                    'message' => null,
                ]);
            });

            test('executes registered AnyOfAssertion and assertion fails', function (): void {
                // Arrange
                $service = new CustomAssertionService([
                    'anyOf' => new AnyOfAssertion(),
                ]);
                $data = 'test-data';
                $expected = [true];
                $actual = false;

                // Act
                $result = $service->execute('anyOf', $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => false,
                    'message' => 'Expected data to be one of [valid], but validator returned invalid',
                ]);
            });

            test('executes registered StrictEqualityAssertion and assertion passes', function (): void {
                // Arrange
                $service = new CustomAssertionService([
                    'strict' => new StrictEqualityAssertion(),
                ]);
                $data = 'test-data';
                $expected = true;
                $actual = true;

                // Act
                $result = $service->execute('strict', $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => true,
                    'message' => null,
                ]);
            });

            test('executes registered StrictEqualityAssertion and assertion fails', function (): void {
                // Arrange
                $service = new CustomAssertionService([
                    'strict' => new StrictEqualityAssertion(),
                ]);
                $data = 'test-data';
                $expected = true;
                $actual = false;

                // Act
                $result = $service->execute('strict', $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => false,
                    'message' => 'Expected data to be valid, but validator returned invalid',
                ]);
            });
        });

        describe('with empty assertions registry', function (): void {
            test('falls back to StrictEqualityAssertion when registry is empty and assertion passes', function (): void {
                // Arrange
                $service = new CustomAssertionService([]);
                $data = 'test-data';
                $expected = 42;
                $actual = 42;

                // Act
                $result = $service->execute('anyAssertion', $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => true,
                    'message' => null,
                ]);
            });

            test('falls back to StrictEqualityAssertion when registry is empty and assertion fails', function (): void {
                // Arrange
                $service = new CustomAssertionService([]);
                $data = 'test-data';
                $expected = true;
                $actual = false;

                // Act
                $result = $service->execute('anyAssertion', $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => false,
                    'message' => 'Expected data to be valid, but validator returned invalid',
                ]);
            });
        });

        describe('edge cases with various data types', function (): void {
            test('handles null data', function (): void {
                // Arrange
                $service = new CustomAssertionService();
                $data = null;
                $expected = true;
                $actual = true;

                // Act
                $result = $service->execute(null, $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => true,
                    'message' => null,
                ]);
            });

            test('handles array data', function (): void {
                // Arrange
                $service = new CustomAssertionService();
                $data = ['key' => 'value'];
                $expected = false;
                $actual = false;

                // Act
                $result = $service->execute(null, $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => true,
                    'message' => null,
                ]);
            });

            test('handles object data', function (): void {
                // Arrange
                $service = new CustomAssertionService();
                $data = new stdClass();
                $expected = true;
                $actual = true;

                // Act
                $result = $service->execute(null, $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => true,
                    'message' => null,
                ]);
            });

            test('handles integer data', function (): void {
                // Arrange
                $service = new CustomAssertionService();
                $data = 123;
                $expected = false;
                $actual = false;

                // Act
                $result = $service->execute(null, $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => true,
                    'message' => null,
                ]);
            });

            test('handles float data', function (): void {
                // Arrange
                $service = new CustomAssertionService();
                $data = 123.45;
                $expected = true;
                $actual = true;

                // Act
                $result = $service->execute(null, $data, $expected, $actual);

                // Assert
                expect($result)->toBe([
                    'passed' => true,
                    'message' => null,
                ]);
            });
        });
    });

    describe('hasAssertion()', function (): void {
        test('returns true when assertion exists in registry', function (): void {
            // Arrange
            $service = new CustomAssertionService([
                'anyOf' => new AnyOfAssertion(),
                'strict' => new StrictEqualityAssertion(),
            ]);

            // Act
            $hasAnyOf = $service->hasAssertion('anyOf');
            $hasStrict = $service->hasAssertion('strict');

            // Assert
            expect($hasAnyOf)->toBeTrue();
            expect($hasStrict)->toBeTrue();
        });

        test('returns false when assertion does not exist in registry', function (): void {
            // Arrange
            $service = new CustomAssertionService([
                'anyOf' => new AnyOfAssertion(),
            ]);

            // Act
            $hasNonexistent = $service->hasAssertion('nonexistent');

            // Assert
            expect($hasNonexistent)->toBeFalse();
        });

        test('returns false when registry is empty', function (): void {
            // Arrange
            $service = new CustomAssertionService([]);

            // Act
            $hasAssertion = $service->hasAssertion('anyAssertion');

            // Assert
            expect($hasAssertion)->toBeFalse();
        });

        test('returns false for empty string assertion name', function (): void {
            // Arrange
            $service = new CustomAssertionService([
                'anyOf' => new AnyOfAssertion(),
            ]);

            // Act
            $hasEmpty = $service->hasAssertion('');

            // Assert
            expect($hasEmpty)->toBeFalse();
        });
    });

    describe('getAssertionNames()', function (): void {
        test('returns all registered assertion names', function (): void {
            // Arrange
            $service = new CustomAssertionService([
                'anyOf' => new AnyOfAssertion(),
                'strict' => new StrictEqualityAssertion(),
            ]);

            // Act
            $names = $service->getAssertionNames();

            // Assert
            expect($names)->toBe(['anyOf', 'strict']);
        });

        test('returns empty array when no assertions registered', function (): void {
            // Arrange
            $service = new CustomAssertionService([]);

            // Act
            $names = $service->getAssertionNames();

            // Assert
            expect($names)->toBe([]);
        });

        test('returns single assertion name when only one registered', function (): void {
            // Arrange
            $service = new CustomAssertionService([
                'anyOf' => new AnyOfAssertion(),
            ]);

            // Act
            $names = $service->getAssertionNames();

            // Assert
            expect($names)->toBe(['anyOf']);
        });

        test('preserves key order from original array', function (): void {
            // Arrange
            $service = new CustomAssertionService([
                'zebra' => new AnyOfAssertion(),
                'alpha' => new StrictEqualityAssertion(),
                'middle' => new AnyOfAssertion(),
            ]);

            // Act
            $names = $service->getAssertionNames();

            // Assert
            expect($names)->toBe(['zebra', 'alpha', 'middle']);
        });
    });
});
