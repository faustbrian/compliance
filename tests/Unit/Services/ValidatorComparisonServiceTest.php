<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Contracts\PrismTestInterface;
use Cline\Prism\Contracts\ValidationResult;
use Cline\Prism\Services\PrismRunner;
use Cline\Prism\Services\ValidatorComparisonService;

use function file_put_contents;
use function json_encode;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

describe('ValidatorComparisonService', function (): void {
    beforeEach(function (): void {
        $this->service = new ValidatorComparisonService();
        $this->runner = new PrismRunner();
        $this->tempDir = sys_get_temp_dir().'/prism_comparison_test_'.uniqid();
        mkdir($this->tempDir);
    });

    afterEach(function (): void {
        // Cleanup temp directory
        if (!is_dir($this->tempDir)) {
            return;
        }

        $files = glob($this->tempDir.'/*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->tempDir);
    });

    describe('compare()', function (): void {
        describe('validator count validation', function (): void {
            test('returns error when no validators provided', function (): void {
                // Arrange
                $validators = [];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result)->toBe([
                    'error' => 'At least two validators required for comparison',
                    'discrepancies' => [],
                ]);
            });

            test('returns error when only one validator provided', function (): void {
                // Arrange
                $validator1 = createValidator('validator1', true);
                $validators = ['validator1' => $validator1];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result)->toBe([
                    'error' => 'At least two validators required for comparison',
                    'discrepancies' => [],
                ]);
            });
        });

        describe('with two validators', function (): void {
            test('returns comparison with no discrepancies when validators agree', function (): void {
                // Arrange
                createTestFile('test.json', [
                    [
                        'description' => 'Test Group',
                        'schema' => ['type' => 'string'],
                        'tests' => [
                            ['description' => 'Test case 1', 'data' => 'value', 'valid' => true],
                            ['description' => 'Test case 2', 'data' => 123, 'valid' => false],
                        ],
                    ],
                ]);

                $validator1 = createValidator('validator1', true);
                $validator2 = createValidator('validator2', true);

                $validators = [
                    'validator1' => $validator1,
                    'validator2' => $validator2,
                ];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result['validators'])->toBe(['validator1', 'validator2']);
                expect($result['total_tests'])->toBe(2);
                expect($result['discrepancies_count'])->toBe(0);
                expect($result['discrepancies'])->toBe([]);
            });

            test('returns comparison with discrepancies when validators disagree', function (): void {
                // Arrange
                createTestFile('test.json', [
                    [
                        'description' => 'Test Group',
                        'schema' => ['type' => 'string'],
                        'tests' => [
                            ['description' => 'Test case with disagreement', 'data' => 'value', 'valid' => true],
                        ],
                    ],
                ]);

                // validator1 validates correctly, validator2 always returns opposite
                $validator1 = createValidator('validator1', true);
                $validator2 = createValidator('validator2', false);

                $validators = [
                    'validator1' => $validator1,
                    'validator2' => $validator2,
                ];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result['validators'])->toBe(['validator1', 'validator2']);
                expect($result['total_tests'])->toBe(1);
                expect($result['discrepancies_count'])->toBe(1);
                expect($result['discrepancies'])->toHaveCount(1);
                expect($result['discrepancies'][0]['test_id'])->toContain('test:0:0');
                expect($result['discrepancies'][0]['description'])->toBe('Test case with disagreement');
                expect($result['discrepancies'][0]['outcomes']['validator1']['actual'])->toBeTrue();
                expect($result['discrepancies'][0]['outcomes']['validator2']['actual'])->toBeFalse();
                expect($result['discrepancies'][0]['agreement'])->toBe('50.0%');
            });
        });

        describe('with three validators', function (): void {
            test('calculates 66.7% agreement when two out of three validators agree', function (): void {
                // Arrange
                createTestFile('test.json', [
                    [
                        'description' => 'Test Group',
                        'schema' => ['type' => 'string'],
                        'tests' => [
                            ['description' => 'Test case', 'data' => 'value', 'valid' => true],
                        ],
                    ],
                ]);

                // Two validators agree, one disagrees
                $validator1 = createValidator('validator1', true);
                $validator2 = createValidator('validator2', true);
                $validator3 = createValidator('validator3', false);

                $validators = [
                    'validator1' => $validator1,
                    'validator2' => $validator2,
                    'validator3' => $validator3,
                ];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result['validators'])->toBe(['validator1', 'validator2', 'validator3']);
                expect($result['total_tests'])->toBe(1);
                expect($result['discrepancies_count'])->toBe(1);
                expect($result['discrepancies'][0]['agreement'])->toBe('66.7%');
            });
        });

        describe('with four validators', function (): void {
            test('calculates 75% agreement when three out of four validators agree', function (): void {
                // Arrange
                createTestFile('test.json', [
                    [
                        'description' => 'Test Group',
                        'schema' => ['type' => 'string'],
                        'tests' => [
                            ['description' => 'Test case', 'data' => 'value', 'valid' => true],
                        ],
                    ],
                ]);

                // Three validators agree (disagree with expected), one agrees with expected
                $validator1 = createValidator('validator1', false);
                $validator2 = createValidator('validator2', false);
                $validator3 = createValidator('validator3', false);
                $validator4 = createValidator('validator4', true);

                $validators = [
                    'validator1' => $validator1,
                    'validator2' => $validator2,
                    'validator3' => $validator3,
                    'validator4' => $validator4,
                ];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result['validators'])->toBe(['validator1', 'validator2', 'validator3', 'validator4']);
                expect($result['total_tests'])->toBe(1);
                expect($result['discrepancies_count'])->toBe(1);
                expect($result['discrepancies'][0]['agreement'])->toBe('75.0%');
            });
        });

        describe('with multiple test cases', function (): void {
            test('identifies all discrepancies across multiple tests', function (): void {
                // Arrange
                createTestFile('test.json', [
                    [
                        'description' => 'Test Group',
                        'schema' => ['type' => 'string'],
                        'tests' => [
                            ['description' => 'Agreeing test case', 'data' => 'value', 'valid' => true],
                            ['description' => 'Disagreeing test case', 'data' => 'invalid', 'valid' => false],
                        ],
                    ],
                    [
                        'description' => 'Test Group 2',
                        'schema' => ['type' => 'number'],
                        'tests' => [
                            ['description' => 'Another agreeing test', 'data' => 123, 'valid' => true],
                        ],
                    ],
                ]);

                // Both validators agree on test 0 and 2, but disagree on test 1
                $validator1 = createValidator('validator1', true);
                $validator2 = createValidator('validator2', true, [1]); // Disagree on index 1

                $validators = [
                    'validator1' => $validator1,
                    'validator2' => $validator2,
                ];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result['validators'])->toBe(['validator1', 'validator2']);
                expect($result['total_tests'])->toBe(3);
                expect($result['discrepancies_count'])->toBe(1);
                expect($result['discrepancies'])->toHaveCount(1);
                expect($result['discrepancies'][0]['description'])->toBe('Disagreeing test case');
            });
        });

        describe('edge cases', function (): void {
            test('handles empty test results from all validators', function (): void {
                // Arrange
                // No test files created, so results will be empty

                $validator1 = createValidator('validator1', true);
                $validator2 = createValidator('validator2', true);

                $validators = [
                    'validator1' => $validator1,
                    'validator2' => $validator2,
                ];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result['validators'])->toBe(['validator1', 'validator2']);
                expect($result['total_tests'])->toBe(0);
                expect($result['discrepancies_count'])->toBe(0);
                expect($result['discrepancies'])->toBe([]);
            });

            test('handles validators with different expected values but same actual', function (): void {
                // Arrange
                createTestFile('test.json', [
                    [
                        'description' => 'Test Group',
                        'schema' => ['type' => 'string'],
                        'tests' => [
                            ['description' => 'Test case', 'data' => 'value', 'valid' => true],
                        ],
                    ],
                ]);

                // Both validators return the same actual (true)
                $validator1 = createValidator('validator1', true);
                $validator2 = createValidator('validator2', true);

                $validators = [
                    'validator1' => $validator1,
                    'validator2' => $validator2,
                ];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                // Same actual means no discrepancy
                expect($result['discrepancies_count'])->toBe(0);
            });

            test('includes all outcome details in discrepancy report', function (): void {
                // Arrange
                createTestFile('test.json', [
                    [
                        'description' => 'Test Group',
                        'schema' => ['type' => 'string'],
                        'tests' => [
                            ['description' => 'Test case', 'data' => 'value', 'valid' => true],
                        ],
                    ],
                ]);

                $validator1 = createValidator('validator1', true);
                $validator2 = createValidator('validator2', false);

                $validators = [
                    'validator1' => $validator1,
                    'validator2' => $validator2,
                ];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result['discrepancies_count'])->toBe(1);
                expect($result['discrepancies'][0]['outcomes']['validator1'])->toBe([
                    'passed' => true,
                    'actual' => true,
                    'expected' => true,
                ]);
                expect($result['discrepancies'][0]['outcomes']['validator2'])->toBe([
                    'passed' => false,
                    'actual' => false,
                    'expected' => true,
                ]);
            });

            test('handles multiple test files correctly', function (): void {
                // Arrange
                createTestFile('test1.json', [
                    [
                        'description' => 'Group 1',
                        'schema' => ['type' => 'string'],
                        'tests' => [
                            ['description' => 'Test 1', 'data' => 'value', 'valid' => true],
                        ],
                    ],
                ]);

                createTestFile('test2.json', [
                    [
                        'description' => 'Group 2',
                        'schema' => ['type' => 'number'],
                        'tests' => [
                            ['description' => 'Test 2', 'data' => 123, 'valid' => true],
                        ],
                    ],
                ]);

                $validator1 = createValidator('validator1', true);
                $validator2 = createValidator('validator2', true);

                $validators = [
                    'validator1' => $validator1,
                    'validator2' => $validator2,
                ];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result['validators'])->toBe(['validator1', 'validator2']);
                expect($result['total_tests'])->toBe(2);
                expect($result['discrepancies_count'])->toBe(0);
            });

            test('correctly calculates agreement with all validators disagreeing 50-50', function (): void {
                // Arrange
                createTestFile('test.json', [
                    [
                        'description' => 'Test Group',
                        'schema' => ['type' => 'string'],
                        'tests' => [
                            ['description' => 'Test case', 'data' => 'value', 'valid' => true],
                        ],
                    ],
                ]);

                $validator1 = createValidator('validator1', true);
                $validator2 = createValidator('validator2', true);
                $validator3 = createValidator('validator3', false);
                $validator4 = createValidator('validator4', false);

                $validators = [
                    'validator1' => $validator1,
                    'validator2' => $validator2,
                    'validator3' => $validator3,
                    'validator4' => $validator4,
                ];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result['discrepancies_count'])->toBe(1);
                expect($result['discrepancies'][0]['agreement'])->toBe('50.0%');
            });

            test('handles validators returning actual opposite of expected', function (): void {
                // Arrange
                createTestFile('test.json', [
                    [
                        'description' => 'Test Group',
                        'schema' => ['type' => 'string'],
                        'tests' => [
                            ['description' => 'Expected valid', 'data' => 'value', 'valid' => true],
                            ['description' => 'Expected invalid', 'data' => 123, 'valid' => false],
                        ],
                    ],
                ]);

                // validator1 validates correctly, validator2 returns opposite
                $validator1 = createValidator('validator1', true);
                $validator2 = createValidator('validator2', false);

                $validators = [
                    'validator1' => $validator1,
                    'validator2' => $validator2,
                ];

                // Act
                $result = $this->service->compare($validators, $this->runner);

                // Assert
                expect($result['total_tests'])->toBe(2);
                expect($result['discrepancies_count'])->toBe(2);
                expect($result['discrepancies'][0]['outcomes']['validator1']['passed'])->toBeTrue();
                expect($result['discrepancies'][0]['outcomes']['validator2']['passed'])->toBeFalse();
                expect($result['discrepancies'][1]['outcomes']['validator1']['passed'])->toBeTrue();
                expect($result['discrepancies'][1]['outcomes']['validator2']['passed'])->toBeFalse();
            });
        });
    });
});

/**
 * Create a test file in the temp directory.
 */
function createTestFile(string $filename, array $testData): void
{
    $filePath = test()->tempDir.'/'.$filename;
    file_put_contents($filePath, json_encode($testData));
}

/**
 * Create a validator that returns expected or opposite validation results.
 *
 * @param string $name              Validator name
 * @param bool   $correctValidation If true, returns expected validation; if false, returns opposite
 * @param array  $disagreeOnIndices Optional array of test indices to disagree on (0-based)
 */
function createValidator(string $name, bool $correctValidation, array $disagreeOnIndices = []): PrismTestInterface
{
    return new class($name, test()->tempDir, $correctValidation, $disagreeOnIndices) implements PrismTestInterface
    {
        private int $testIndex = 0;

        public function __construct(
            private readonly string $name,
            private readonly string $testDirectory,
            private readonly bool $correctValidation,
            private readonly array $disagreeOnIndices,
        ) {}

        public function getName(): string
        {
            return $this->name;
        }

        public function getValidatorClass(): string
        {
            return $this->name.'Validator';
        }

        public function getTestDirectory(): string
        {
            return $this->testDirectory;
        }

        public function validate(mixed $data, mixed $schema): ValidationResult
        {
            $shouldDisagreeOnThisTest = in_array($this->testIndex, $this->disagreeOnIndices, true);
            ++$this->testIndex;

            // Determine if this should be valid based on type checking
            $expected = $this->checkType($data, $schema);

            // If correctValidation is false OR if we should disagree on this specific test, flip the result
            $shouldFlip = !$this->correctValidation || $shouldDisagreeOnThisTest;
            $actual = $shouldFlip ? !$expected : $expected;

            return new readonly class($actual) implements ValidationResult
            {
                public function __construct(
                    private bool $valid,
                ) {}

                public function isValid(): bool
                {
                    return $this->valid;
                }

                public function getErrors(): array
                {
                    return $this->valid ? [] : ['Validation failed'];
                }
            };
        }

        public function getTestFilePatterns(): array
        {
            return ['*.json'];
        }

        public function decodeJson(string $json): mixed
        {
            return json_decode($json, true);
        }

        public function shouldIncludeFile(string $filePath): bool
        {
            return true;
        }

        private function checkType(mixed $data, mixed $schema): bool
        {
            if (!is_array($schema) || !isset($schema['type'])) {
                return true;
            }

            return match ($schema['type']) {
                'string' => is_string($data),
                'number' => is_int($data) || is_float($data),
                'boolean' => is_bool($data),
                'array' => is_array($data),
                'object' => is_object($data),
                'null' => $data === null,
                default => true,
            };
        }
    };
}
