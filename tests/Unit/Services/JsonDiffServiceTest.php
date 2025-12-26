<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Services;

use Cline\Prism\Services\JsonDiffService;
use stdClass;

use function beforeEach;
use function describe;
use function expect;
use function it;
use function PHPUnit\Framework\assertStringContainsString;
use function str_repeat;

/**
 * @covers \Cline\Prism\Services\JsonDiffService
 */
describe('JsonDiffService', function (): void {
    beforeEach(function (): void {
        // Arrange
        $this->service = new JsonDiffService();
    });

    describe('diff()', function (): void {
        describe('identical values', function (): void {
            it('returns identical message for same null values', function (): void {
                // Arrange
                $expected = null;
                $actual = null;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                expect($result)->toBe('Values are identical');
            });

            it('returns identical message for same boolean values', function (): void {
                // Arrange
                $expected = true;
                $actual = true;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                expect($result)->toBe('Values are identical');
            });

            it('returns identical message for same integer values', function (): void {
                // Arrange
                $expected = 42;
                $actual = 42;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                expect($result)->toBe('Values are identical');
            });

            it('returns identical message for same float values', function (): void {
                // Arrange
                $expected = 3.14;
                $actual = 3.14;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                expect($result)->toBe('Values are identical');
            });

            it('returns identical message for same string values', function (): void {
                // Arrange
                $expected = 'test';
                $actual = 'test';

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                expect($result)->toBe('Values are identical');
            });

            it('returns identical message for same array values', function (): void {
                // Arrange
                $expected = ['key' => 'value'];
                $actual = ['key' => 'value'];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                expect($result)->toBe('Values are identical');
            });
        });

        describe('type mismatches', function (): void {
            it('detects null vs boolean type mismatch', function (): void {
                // Arrange
                $expected = null;
                $actual = true;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Type mismatch:', $result);
                assertStringContainsString('Expected: null (null)', $result);
                assertStringContainsString('Actual: boolean (true)', $result);
            });

            it('detects null vs integer type mismatch', function (): void {
                // Arrange
                $expected = null;
                $actual = 42;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Type mismatch:', $result);
                assertStringContainsString('Expected: null (null)', $result);
                assertStringContainsString('Actual: integer (42)', $result);
            });

            it('detects null vs float type mismatch', function (): void {
                // Arrange
                $expected = null;
                $actual = 3.14;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Type mismatch:', $result);
                assertStringContainsString('Expected: null (null)', $result);
                assertStringContainsString('Actual: float (3.14)', $result);
            });

            it('detects null vs string type mismatch', function (): void {
                // Arrange
                $expected = null;
                $actual = 'test';

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Type mismatch:', $result);
                assertStringContainsString('Expected: null (null)', $result);
                assertStringContainsString('Actual: string ("test")', $result);
            });

            it('detects null vs array type mismatch', function (): void {
                // Arrange
                $expected = null;
                $actual = [];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Type mismatch:', $result);
                assertStringContainsString('Expected: null (null)', $result);
                assertStringContainsString('Actual: array', $result);
            });

            it('detects null vs object type mismatch', function (): void {
                // Arrange
                $expected = null;
                $actual = new stdClass();

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Type mismatch:', $result);
                assertStringContainsString('Expected: null (null)', $result);
                assertStringContainsString('Actual: object', $result);
            });

            it('detects boolean vs integer type mismatch', function (): void {
                // Arrange
                $expected = true;
                $actual = 1;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Type mismatch:', $result);
                assertStringContainsString('Expected: boolean (true)', $result);
                assertStringContainsString('Actual: integer (1)', $result);
            });

            it('detects integer vs float type mismatch', function (): void {
                // Arrange
                $expected = 42;
                $actual = 42.0;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Type mismatch:', $result);
                assertStringContainsString('Expected: integer (42)', $result);
                assertStringContainsString('Actual: float (42)', $result);
            });

            it('detects string vs array type mismatch', function (): void {
                // Arrange
                $expected = 'test';
                $actual = ['test'];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Type mismatch:', $result);
                assertStringContainsString('Expected: string ("test")', $result);
                assertStringContainsString('Actual: array', $result);
            });
        });

        describe('primitive value mismatches', function (): void {
            it('detects boolean value mismatch', function (): void {
                // Arrange
                $expected = true;
                $actual = false;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
                assertStringContainsString('Expected: true', $result);
                assertStringContainsString('Actual: false', $result);
            });

            it('detects integer value mismatch', function (): void {
                // Arrange
                $expected = 42;
                $actual = 100;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
                assertStringContainsString('Expected: 42', $result);
                assertStringContainsString('Actual: 100', $result);
            });

            it('detects float value mismatch', function (): void {
                // Arrange
                $expected = 3.14;
                $actual = 2.71;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
                assertStringContainsString('Expected: 3.14', $result);
                assertStringContainsString('Actual: 2.71', $result);
            });

            it('detects string value mismatch', function (): void {
                // Arrange
                $expected = 'expected';
                $actual = 'actual';

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
                assertStringContainsString('Expected: "expected"', $result);
                assertStringContainsString('Actual: "actual"', $result);
            });

            it('handles empty strings', function (): void {
                // Arrange
                $expected = '';
                $actual = 'not empty';

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
                assertStringContainsString('Expected: ""', $result);
                assertStringContainsString('Actual: "not empty"', $result);
            });

            it('handles negative integers', function (): void {
                // Arrange
                $expected = -42;
                $actual = 42;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
                assertStringContainsString('Expected: -42', $result);
                assertStringContainsString('Actual: 42', $result);
            });

            it('handles negative floats', function (): void {
                // Arrange
                $expected = -3.14;
                $actual = 3.14;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
                assertStringContainsString('Expected: -3.14', $result);
                assertStringContainsString('Actual: 3.14', $result);
            });

            it('handles zero values', function (): void {
                // Arrange
                $expected = 0;
                $actual = 1;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
                assertStringContainsString('Expected: 0', $result);
                assertStringContainsString('Actual: 1', $result);
            });
        });

        describe('complex data structure mismatches', function (): void {
            it('detects array value mismatch', function (): void {
                // Arrange
                $expected = ['key' => 'expected'];
                $actual = ['key' => 'actual'];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
                assertStringContainsString('Expected:', $result);
                assertStringContainsString('Actual:', $result);
                assertStringContainsString('"expected"', $result);
                assertStringContainsString('"actual"', $result);
            });

            it('detects nested array mismatch', function (): void {
                // Arrange
                $expected = [
                    'level1' => [
                        'level2' => [
                            'value' => 'expected',
                        ],
                    ],
                ];
                $actual = [
                    'level1' => [
                        'level2' => [
                            'value' => 'actual',
                        ],
                    ],
                ];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
                assertStringContainsString('level1', $result);
                assertStringContainsString('level2', $result);
                assertStringContainsString('"expected"', $result);
                assertStringContainsString('"actual"', $result);
            });

            it('detects object mismatch', function (): void {
                // Arrange
                $expected = new stdClass();
                $expected->prop = 'expected';

                $actual = new stdClass();
                $actual->prop = 'actual';

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
                assertStringContainsString('Expected:', $result);
                assertStringContainsString('Actual:', $result);
                assertStringContainsString('"expected"', $result);
                assertStringContainsString('"actual"', $result);
            });

            it('handles empty arrays', function (): void {
                // Arrange
                $expected = [];
                $actual = ['key' => 'value'];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
                assertStringContainsString('Expected:', $result);
                assertStringContainsString('Actual:', $result);
            });

            it('handles arrays with numeric keys', function (): void {
                // Arrange
                $expected = [1, 2, 3];
                $actual = [4, 5, 6];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
                assertStringContainsString('Expected:', $result);
                assertStringContainsString('Actual:', $result);
            });

            it('handles arrays with mixed keys', function (): void {
                // Arrange
                $expected = ['string_key' => 'value', 0 => 'indexed'];
                $actual = ['string_key' => 'different', 0 => 'changed'];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
                assertStringContainsString('string_key', $result);
            });

            it('handles unicode characters in arrays', function (): void {
                // Arrange
                $expected = ['emoji' => '🎉', 'unicode' => 'Привет'];
                $actual = ['emoji' => '🎊', 'unicode' => 'Hello'];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
                assertStringContainsString('🎉', $result);
                assertStringContainsString('🎊', $result);
                assertStringContainsString('Привет', $result);
                assertStringContainsString('Hello', $result);
            });

            it('handles arrays with special characters', function (): void {
                // Arrange
                $expected = ['path' => '/path/to/file', 'url' => 'https://example.com'];
                $actual = ['path' => '/other/path', 'url' => 'https://other.com'];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
                assertStringContainsString('/path/to/file', $result);
                assertStringContainsString('https://example.com', $result);
            });
        });

        describe('edge cases', function (): void {
            it('handles very long strings', function (): void {
                // Arrange
                $expected = str_repeat('a', 1_000);
                $actual = str_repeat('b', 1_000);

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
                assertStringContainsString('Expected:', $result);
                assertStringContainsString('Actual:', $result);
            });

            it('handles deeply nested arrays', function (): void {
                // Arrange
                $expected = ['a' => ['b' => ['c' => ['d' => ['e' => 'expected']]]]];
                $actual = ['a' => ['b' => ['c' => ['d' => ['e' => 'actual']]]]];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
                assertStringContainsString('"expected"', $result);
                assertStringContainsString('"actual"', $result);
            });

            it('handles arrays with null values', function (): void {
                // Arrange
                $expected = ['key' => null];
                $actual = ['key' => 'value'];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
                assertStringContainsString('null', $result);
            });

            it('handles mixed type arrays', function (): void {
                // Arrange
                $expected = [1, 'string', true, null, 3.14];
                $actual = [2, 'different', false, 'not null', 2.71];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
            });

            it('handles boolean true in formatValue', function (): void {
                // Arrange
                $expected = true;
                $actual = false;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('true', $result);
                assertStringContainsString('false', $result);
            });

            it('handles boolean false in formatValue', function (): void {
                // Arrange
                $expected = false;
                $actual = true;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('false', $result);
                assertStringContainsString('true', $result);
            });

            it('handles scientific notation floats', function (): void {
                // Arrange
                $expected = 1.23e-10;
                $actual = 4.56e-10;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
            });

            it('handles strings with quotes', function (): void {
                // Arrange
                $expected = 'He said "hello"';
                $actual = 'She said "goodbye"';

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
            });

            it('handles strings with newlines', function (): void {
                // Arrange
                $expected = "line1\nline2";
                $actual = "line1\nline3";

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Value mismatch:', $result);
            });

            it('handles empty objects', function (): void {
                // Arrange
                $expected = new stdClass();
                $actual = new stdClass();
                $actual->prop = 'value';

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
            });

            it('handles arrays with boolean values', function (): void {
                // Arrange
                $expected = ['flag' => true];
                $actual = ['flag' => false];

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Data structure mismatch:', $result);
                assertStringContainsString('true', $result);
                assertStringContainsString('false', $result);
            });

            it('handles comparison of zero float and zero int', function (): void {
                // Arrange
                $expected = 0;
                $actual = 0.0;

                // Act
                $result = $this->service->diff($expected, $actual);

                // Assert
                assertStringContainsString('Type mismatch:', $result);
                assertStringContainsString('integer', $result);
                assertStringContainsString('float', $result);
            });
        });
    });
});
