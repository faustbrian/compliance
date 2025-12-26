<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Assertions\AnyOfAssertion;

describe('AnyOfAssertion', function (): void {
    beforeEach(function (): void {
        $this->assertion = new AnyOfAssertion();
    });

    describe('assert()', function (): void {
        describe('with scalar expected values', function (): void {
            test('returns true when scalar expected matches actual (both true)', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = true;
                $actual = true;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('returns true when scalar expected matches actual (both false)', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = false;
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('returns false when scalar expected does not match actual (true vs false)', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = true;
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('returns false when scalar expected does not match actual (false vs true)', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = false;
                $actual = true;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('performs strict comparison with scalar values (string "1" vs int 1)', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = '1';
                $actual = 1;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('performs strict comparison with scalar values (int 0 vs bool false)', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = 0;
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('handles null expected value', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = null;
                $actual = null;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('returns false when null expected does not match actual', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = null;
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });
        });

        describe('with array expected values', function (): void {
            test('returns true when actual is in expected array (true in [true, false])', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true, false];
                $actual = true;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('returns true when actual is in expected array (false in [true, false])', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true, false];
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('returns false when actual is not in expected array', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true];
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('performs strict comparison in array (string "1" not in [1, 2])', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [1, 2];
                $actual = '1';

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('performs strict comparison in array (int 0 not in [false])', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [false];
                $actual = 0;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('handles empty array expected (actual never matches)', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [];
                $actual = true;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('handles null in expected array', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [null, true, false];
                $actual = null;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('handles single-element array with matching value', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true];
                $actual = true;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('handles multiple identical values in expected array', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true, true, true];
                $actual = true;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('handles mixed types in expected array', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true, 1, 'yes', null];
                $actual = 1;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });
        });

        describe('edge cases', function (): void {
            test('handles empty data parameter (data is not used in logic)', function (): void {
                // Arrange
                $data = [];
                $expected = true;
                $actual = true;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('handles null data parameter (data is not used in logic)', function (): void {
                // Arrange
                $data = null;
                $expected = false;
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('handles string expected value', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = 'valid';
                $actual = 'valid';

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('handles integer expected value', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = 42;
                $actual = 42;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('handles float expected value with strict comparison', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = 3.14;
                $actual = 3.14;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });
        });
    });

    describe('getName()', function (): void {
        test('returns "AnyOf" as the assertion name', function (): void {
            // Arrange
            // (no setup needed)

            // Act
            $name = $this->assertion->getName();

            // Assert
            expect($name)->toBe('AnyOf');
        });

        test('returns consistent name across multiple calls', function (): void {
            // Arrange
            // (no setup needed)

            // Act
            $name1 = $this->assertion->getName();
            $name2 = $this->assertion->getName();

            // Assert
            expect($name1)->toBe($name2);
            expect($name1)->toBe('AnyOf');
        });
    });

    describe('getFailureMessage()', function (): void {
        describe('with scalar expected values', function (): void {
            test('generates message when expected true but got false', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = true;
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });

            test('generates message when expected false but got true', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = false;
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });

            test('uses "valid" label for true expected value', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = true;
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toContain('Expected data to be valid');
            });

            test('uses "invalid" label for false expected value', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = false;
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toContain('Expected data to be invalid');
            });

            test('uses "valid" label for true actual value', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = false;
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toContain('validator returned valid');
            });

            test('uses "invalid" label for false actual value', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = true;
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toContain('validator returned invalid');
            });

            test('handles null expected value in message', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = null;
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });

            test('handles null actual value in message', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = true;
                $actual = null;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });

            test('handles empty string expected value (falsy)', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = '';
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });

            test('handles zero expected value (falsy)', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = 0;
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });
        });

        describe('with array expected values', function (): void {
            test('generates message with list of expected values (both true and false)', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true, false];
                $actual = null;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be one of [valid, invalid], but validator returned invalid');
            });

            test('generates message with single expected value in array', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true];
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be one of [valid], but validator returned invalid');
            });

            test('maps true values to "valid" in expected list', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true, true];
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be one of [valid, valid], but validator returned invalid');
            });

            test('maps false values to "invalid" in expected list', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [false, false];
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be one of [invalid, invalid], but validator returned valid');
            });

            test('maps mixed truthy/falsy values correctly', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true, false, null, 1, 0, ''];
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be one of [valid, invalid, invalid, valid, invalid, invalid], but validator returned valid');
            });

            test('handles empty array expected', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [];
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be one of [], but validator returned valid');
            });

            test('uses "valid" for true actual value with array expected', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [false];
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toContain('validator returned valid');
            });

            test('uses "invalid" for false actual value with array expected', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true];
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toContain('validator returned invalid');
            });

            test('uses "invalid" for null actual value with array expected', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true, false];
                $actual = null;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toContain('validator returned invalid');
            });

            test('uses "invalid" for zero actual value with array expected', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true, false];
                $actual = 0;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toContain('validator returned invalid');
            });

            test('uses "valid" for non-zero actual value with array expected', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [false];
                $actual = 1;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toContain('validator returned valid');
            });

            test('handles large array of expected values', function (): void {
                // Arrange
                $data = ['test' => 'data'];
                $expected = [true, false, true, false, true];
                $actual = null;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be one of [valid, invalid, valid, invalid, valid], but validator returned invalid');
            });
        });

        describe('edge cases', function (): void {
            test('handles empty data parameter (data is not used in message generation)', function (): void {
                // Arrange
                $data = [];
                $expected = true;
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });

            test('handles null data parameter (data is not used in message generation)', function (): void {
                // Arrange
                $data = null;
                $expected = false;
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });

            test('handles complex data structures (data is not used in message)', function (): void {
                // Arrange
                $data = ['nested' => ['deep' => ['value' => 123]]];
                $expected = [true, false];
                $actual = null;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be one of [valid, invalid], but validator returned invalid');
            });
        });
    });
});
