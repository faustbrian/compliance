<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Assertions\StrictEqualityAssertion;

describe('StrictEqualityAssertion', function (): void {
    beforeEach(function (): void {
        $this->assertion = new StrictEqualityAssertion();
    });

    describe('assert()', function (): void {
        describe('returns true when values are strictly equal', function (): void {
            test('both true', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = true;
                $actual = true;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('both false', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = false;
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('both null', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = null;
                $actual = null;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('identical integers', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = 42;
                $actual = 42;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('identical strings', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = 'valid';
                $actual = 'valid';

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('identical arrays', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = ['a' => 1, 'b' => 2];
                $actual = ['a' => 1, 'b' => 2];

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });

            test('same object instance', function (): void {
                // Arrange
                $data = 'test-data';
                $object = new stdClass();
                $expected = $object;
                $actual = $object;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeTrue();
            });
        });

        describe('returns false when values are not strictly equal', function (): void {
            test('true vs false', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = true;
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('false vs true', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = false;
                $actual = true;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('integer 1 vs boolean true (type difference)', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = 1;
                $actual = true;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('integer 0 vs boolean false (type difference)', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = 0;
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('string "1" vs integer 1 (type difference)', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = '1';
                $actual = 1;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('null vs false', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = null;
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('empty string vs false', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = '';
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('empty array vs false', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = [];
                $actual = false;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('different object instances', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = new stdClass();
                $actual = new stdClass();

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('different integers', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = 42;
                $actual = 43;

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('different strings', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = 'valid';
                $actual = 'invalid';

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('arrays with different values', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = ['a' => 1, 'b' => 2];
                $actual = ['a' => 1, 'b' => 3];

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });

            test('arrays with different keys', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = ['a' => 1, 'b' => 2];
                $actual = ['a' => 1, 'c' => 2];

                // Act
                $result = $this->assertion->assert($data, $expected, $actual);

                // Assert
                expect($result)->toBeFalse();
            });
        });
    });

    describe('getName()', function (): void {
        test('returns StrictEquality', function (): void {
            // Act
            $name = $this->assertion->getName();

            // Assert
            expect($name)->toBe('StrictEquality');
        });
    });

    describe('getFailureMessage()', function (): void {
        describe('formats message correctly for boolean values', function (): void {
            test('expected true, got false', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = true;
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });

            test('expected false, got true', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = false;
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });
        });

        describe('formats message for truthy/falsy values', function (): void {
            test('expected 1 (truthy), got false', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = 1;
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });

            test('expected 0 (falsy), got true', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = 0;
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });

            test('expected null (falsy), got true', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = null;
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });

            test('expected empty string (falsy), got true', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = '';
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });

            test('expected empty array (falsy), got true', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = [];
                $actual = true;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });

            test('expected string "test" (truthy), got false', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = 'test';
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });

            test('expected non-empty array (truthy), got false', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = ['a' => 1];
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });

            test('expected object (truthy), got false', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = new stdClass();
                $actual = false;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });
        });

        describe('formats message for truthy actual values', function (): void {
            test('expected false, got 1 (truthy)', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = false;
                $actual = 1;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });

            test('expected false, got string "test" (truthy)', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = false;
                $actual = 'test';

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });

            test('expected false, got non-empty array (truthy)', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = false;
                $actual = ['a' => 1];

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });

            test('expected false, got object (truthy)', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = false;
                $actual = new stdClass();

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be invalid, but validator returned valid');
            });
        });

        describe('formats message for falsy actual values', function (): void {
            test('expected true, got 0 (falsy)', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = true;
                $actual = 0;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });

            test('expected true, got null (falsy)', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = true;
                $actual = null;

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });

            test('expected true, got empty string (falsy)', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = true;
                $actual = '';

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });

            test('expected true, got empty array (falsy)', function (): void {
                // Arrange
                $data = 'test-data';
                $expected = true;
                $actual = [];

                // Act
                $message = $this->assertion->getFailureMessage($data, $expected, $actual);

                // Assert
                expect($message)->toBe('Expected data to be valid, but validator returned invalid');
            });
        });
    });
});
