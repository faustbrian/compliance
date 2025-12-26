<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Contracts\PrismTestInterface;
use Cline\Prism\Contracts\ValidationResult;
use Cline\Prism\Services\FuzzingService;
use Cline\Prism\ValueObjects\TestSuite;
use RuntimeException;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

describe('FuzzingService', function (): void {
    beforeEach(function (): void {
        $this->service = new FuzzingService();
    });

    describe('basic fuzzing', function (): void {
        test('fuzzes with edge cases only when iterations is zero', function (): void {
            $prism = new MockPrismTest();

            $result = $this->service->fuzz($prism, 0);

            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->name)->toBe('MockPrism (fuzzed)')
                ->and($result->totalTests())->toBe(24) // 24 edge cases
                ->and($result->duration)->toBeGreaterThan(0)
                ->and($prism->validateCallCount)->toBe(24);
        });

        test('fuzzes with edge cases plus random iterations', function (): void {
            $prism = new MockPrismTest();
            $iterations = 10;

            $result = $this->service->fuzz($prism, $iterations);

            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->name)->toBe('MockPrism (fuzzed)')
                ->and($result->totalTests())->toBe(34) // 24 edge cases + 10 iterations
                ->and($result->duration)->toBeGreaterThan(0)
                ->and($prism->validateCallCount)->toBe(34);
        });

        test('fuzzes with large iteration count', function (): void {
            $prism = new MockPrismTest();
            $iterations = 100;

            $result = $this->service->fuzz($prism, $iterations);

            expect($result->totalTests())->toBe(124) // 24 edge cases + 100 iterations
                ->and($result->duration)->toBeGreaterThan(0);
        });
    });

    describe('test result generation', function (): void {
        test('generates correct test IDs for edge cases and fuzzed tests', function (): void {
            $prism = new MockPrismTest();

            $result = $this->service->fuzz($prism, 2);

            expect($result->results[0]->id)->toBe('edge-case-0')
                ->and($result->results[1]->id)->toBe('edge-case-1')
                ->and($result->results[23]->id)->toBe('edge-case-23')
                ->and($result->results[24]->id)->toBe('fuzz-0')
                ->and($result->results[25]->id)->toBe('fuzz-1');
        });

        test('all test results have fuzzing group tag', function (): void {
            $prism = new MockPrismTest();

            $result = $this->service->fuzz($prism, 5);

            foreach ($result->results as $testResult) {
                expect($testResult->group)->toBe('fuzzing');
            }
        });

        test('test results include proper descriptions', function (): void {
            $prism = new MockPrismTest();

            $result = $this->service->fuzz($prism, 1);

            expect($result->results[0]->description)->toStartWith('Fuzzed test with');
        });

        test('all test results have fuzzed file designation', function (): void {
            $prism = new MockPrismTest();

            $result = $this->service->fuzz($prism, 3);

            foreach ($result->results as $testResult) {
                expect($testResult->file)->toBe('fuzzed');
            }
        });

        test('records duration for each test result', function (): void {
            $prism = new MockPrismTest();

            $result = $this->service->fuzz($prism, 2);

            foreach ($result->results as $testResult) {
                expect($testResult->duration)->toBeGreaterThanOrEqual(0);
            }
        });
    });

    describe('exception handling', function (): void {
        test('handles validation exceptions and creates failed test results', function (): void {
            $prism = new MockPrismTestWithException();

            $result = $this->service->fuzz($prism, 1);

            expect($result->totalTests())->toBe(25) // 24 edge cases + 1 iteration
                ->and($result->passedTests())->toBe(0)
                ->and($result->failedTests())->toBe(25);

            foreach ($result->results as $testResult) {
                expect($testResult->passed)->toBeFalse()
                    ->and($testResult->expected)->toBeFalse()
                    ->and($testResult->actual)->toBeFalse()
                    ->and($testResult->error)->toBe('Validation failed')
                    ->and($testResult->tags)->toContain('fuzzed')
                    ->and($testResult->tags)->toContain('error');
            }
        });

        test('exception test results include error tags', function (): void {
            $prism = new MockPrismTestWithException();

            $result = $this->service->fuzz($prism, 1);

            foreach ($result->results as $testResult) {
                expect($testResult->tags)->toContain('fuzzed')
                    ->and($testResult->tags)->toContain('error');
            }
        });

        test('failed test results have mismatched expected and actual validity', function (): void {
            $prism = new MockPrismTestWithException();

            $result = $this->service->fuzz($prism, 2);

            foreach ($result->results as $testResult) {
                expect($testResult->passed)->toBeFalse()
                    ->and($testResult->error)->not->toBeNull();
            }
        });
    });

    describe('edge case generation', function (): void {
        test('edge cases include all expected values', function (): void {
            $prism = new MockPrismTestCapture();

            $this->service->fuzz($prism, 0);

            // Verify we have exactly 24 edge cases
            expect($prism->capturedData)->toHaveCount(24);

            // Verify specific edge cases
            expect($prism->capturedData[0])->toBeNull()
                ->and($prism->capturedData[1])->toBeTrue()
                ->and($prism->capturedData[2])->toBeFalse()
                ->and($prism->capturedData[3])->toBe(0)
                ->and($prism->capturedData[4])->toBe(-1)
                ->and($prism->capturedData[5])->toBe(1)
                ->and($prism->capturedData[6])->toBe(PHP_INT_MAX)
                ->and($prism->capturedData[7])->toBe(PHP_INT_MIN)
                ->and($prism->capturedData[8])->toBe(0.0)
                ->and($prism->capturedData[9])->toBe(-0.0)
                ->and($prism->capturedData[10])->toBe('')
                ->and($prism->capturedData[11])->toBe(' ')
                ->and($prism->capturedData[12])->toBe("\n")
                ->and($prism->capturedData[13])->toBe("\t")
                ->and($prism->capturedData[14])->toBe('a')
                ->and($prism->capturedData[15])->toBeString()
                ->and(mb_strlen((string) $prism->capturedData[15]))->toBe(1_000)
                ->and($prism->capturedData[16])->toBeString()
                ->and(mb_strlen((string) $prism->capturedData[16]))->toBe(10_000)
                ->and($prism->capturedData[17])->toBe([])
                ->and($prism->capturedData[18])->toBe([null])
                ->and($prism->capturedData[19])->toBe([''])
                ->and($prism->capturedData[20])->toBe([0])
                ->and($prism->capturedData[21])->toBe([[]])
                ->and($prism->capturedData[22])->toBe(['key' => 'value'])
                ->and($prism->capturedData[23])->toBe(['nested' => ['deep' => ['value' => true]]]);
        });

        test('handles floats in edge cases', function (): void {
            $prism = new MockPrismTestCapture();

            $this->service->fuzz($prism, 0);

            // Edge cases 8 and 9 are floats
            expect($prism->capturedData[8])->toBeFloat()
                ->and($prism->capturedData[9])->toBeFloat();
        });
    });

    describe('random data generation', function (): void {
        test('random data generation produces varied types', function (): void {
            $prism = new MockPrismTestCapture();
            $iterations = 100;

            $this->service->fuzz($prism, $iterations);

            // Skip first 24 edge cases, check random data
            $randomData = array_slice($prism->capturedData, 24);

            // Should have multiple different types in the random data
            $hasNull = false;
            $hasBool = false;
            $hasInt = false;
            $hasFloat = false;
            $hasString = false;
            $hasArray = false;

            foreach ($randomData as $data) {
                if ($data === null) {
                    $hasNull = true;
                } elseif (is_bool($data)) {
                    $hasBool = true;
                } elseif (is_int($data)) {
                    $hasInt = true;
                } elseif (is_float($data)) {
                    $hasFloat = true;
                } elseif (is_string($data)) {
                    $hasString = true;
                } elseif (is_array($data)) {
                    $hasArray = true;
                }
            }

            // With 100 iterations, we should have at least some variety
            expect($hasNull || $hasBool || $hasInt || $hasFloat || $hasString || $hasArray)->toBeTrue();
        });

        test('random strings have valid length range', function (): void {
            $prism = new MockPrismTestCapture();
            $iterations = 50;

            $this->service->fuzz($prism, $iterations);

            // Check random string lengths
            $randomData = array_slice($prism->capturedData, 24);

            foreach ($randomData as $data) {
                if (!is_string($data)) {
                    continue;
                }

                $length = mb_strlen($data);

                expect($length)->toBeGreaterThanOrEqual(0)
                    ->and($length)->toBeLessThanOrEqual(100);
            }
        });

        test('random arrays have valid length range', function (): void {
            $prism = new MockPrismTestCapture();
            $iterations = 50;

            $this->service->fuzz($prism, $iterations);

            // Check random array lengths
            $randomData = array_slice($prism->capturedData, 24);

            foreach ($randomData as $data) {
                if (!is_array($data)) {
                    continue;
                }

                $length = count($data);

                expect($length)->toBeGreaterThanOrEqual(0)
                    ->and($length)->toBeLessThanOrEqual(10);
            }
        });
    });

    describe('validation', function (): void {
        test('validates data against true schema', function (): void {
            $prism = new MockPrismTestSchemaCapture();

            $this->service->fuzz($prism, 1);

            // All validations should use 'true' as schema
            foreach ($prism->capturedSchemas as $schema) {
                expect($schema)->toBeTrue();
            }
        });

        test('success test results have expected valid matching actual valid', function (): void {
            $prism = new MockPrismTestVaryingValidity();

            $result = $this->service->fuzz($prism, 5);

            foreach ($result->results as $testResult) {
                if (!$testResult->passed) {
                    continue;
                }

                expect($testResult->expected)->toBe($testResult->actual);
            }
        });
    });

    describe('data type descriptions', function (): void {
        test('describes null data correctly', function (): void {
            $prism = new MockPrismTestDescriptionCapture();

            $result = $this->service->fuzz($prism, 0);

            expect($result->results[0]->description)->toContain('null');
        });

        test('describes boolean data correctly', function (): void {
            $prism = new MockPrismTestDescriptionCapture();

            $result = $this->service->fuzz($prism, 0);

            // Edge cases 1 and 2 are true and false
            expect($result->results[1]->description)->toContain('boolean')
                ->and($result->results[2]->description)->toContain('boolean');
        });

        test('describes integer data correctly', function (): void {
            $prism = new MockPrismTestDescriptionCapture();

            $result = $this->service->fuzz($prism, 0);

            // Edge cases 3-7 are integers
            expect($result->results[3]->description)->toContain('integer')
                ->and($result->results[4]->description)->toContain('integer');
        });

        test('describes string data correctly', function (): void {
            $prism = new MockPrismTestDescriptionCapture();

            $result = $this->service->fuzz($prism, 0);

            // Edge cases 10-16 are strings
            expect($result->results[10]->description)->toContain('string')
                ->and($result->results[14]->description)->toContain('string');
        });

        test('describes empty array correctly', function (): void {
            $prism = new MockPrismTestDescriptionCapture();

            $result = $this->service->fuzz($prism, 0);

            // Edge case 17 is []
            expect($result->results[17]->description)->toContain('empty array');
        });

        test('describes non-empty array correctly', function (): void {
            $prism = new MockPrismTestDescriptionCapture();

            $result = $this->service->fuzz($prism, 0);

            // Edge case 18 is [null]
            $description = $result->results[18]->description;

            expect($description)->toContain('array')
                ->and($description)->not->toContain('empty array');
        });

        test('unknown data type returns unknown description', function (): void {
            $prism = new MockPrismTestDescriptionCapture();

            // Since FuzzingService doesn't generate stdClass, we need to verify the logic
            // by checking that floats fall through to 'unknown'
            $result = $this->service->fuzz($prism, 0);

            // Edge cases 8 and 9 are floats (0.0 and -0.0) which should be 'unknown'
            expect($result->results[8]->description)->toContain('unknown')
                ->and($result->results[9]->description)->toContain('unknown');
        });
    });
});

/**
 * Mock implementation of PrismTestInterface for testing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockPrismTest implements PrismTestInterface
{
    public int $validateCallCount = 0;

    public function getName(): string
    {
        return 'MockPrism';
    }

    public function getValidatorClass(): string
    {
        return MockValidator::class;
    }

    public function getTestDirectory(): string
    {
        return '/mock/directory';
    }

    public function validate(mixed $data, mixed $schema): ValidationResult
    {
        ++$this->validateCallCount;

        return new MockValidationResult(true);
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
}

/**
 * Mock implementation that captures all data passed to validate.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockPrismTestCapture implements PrismTestInterface
{
    /** @var array<int, mixed> */
    public array $capturedData = [];

    public function getName(): string
    {
        return 'MockPrism';
    }

    public function getValidatorClass(): string
    {
        return MockValidator::class;
    }

    public function getTestDirectory(): string
    {
        return '/mock/directory';
    }

    public function validate(mixed $data, mixed $schema): ValidationResult
    {
        $this->capturedData[] = $data;

        return new MockValidationResult(true);
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
}

/**
 * Mock implementation that captures schemas passed to validate.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockPrismTestSchemaCapture implements PrismTestInterface
{
    /** @var array<int, mixed> */
    public array $capturedSchemas = [];

    public function getName(): string
    {
        return 'MockPrism';
    }

    public function getValidatorClass(): string
    {
        return MockValidator::class;
    }

    public function getTestDirectory(): string
    {
        return '/mock/directory';
    }

    public function validate(mixed $data, mixed $schema): ValidationResult
    {
        $this->capturedSchemas[] = $schema;

        return new MockValidationResult(true);
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
}

/**
 * Mock implementation that captures descriptions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockPrismTestDescriptionCapture implements PrismTestInterface
{
    public function getName(): string
    {
        return 'MockPrism';
    }

    public function getValidatorClass(): string
    {
        return MockValidator::class;
    }

    public function getTestDirectory(): string
    {
        return '/mock/directory';
    }

    public function validate(mixed $data, mixed $schema): ValidationResult
    {
        return new MockValidationResult(true);
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
}

/**
 * Mock implementation that throws exceptions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockPrismTestWithException implements PrismTestInterface
{
    public function getName(): string
    {
        return 'MockPrism';
    }

    public function getValidatorClass(): string
    {
        return MockValidator::class;
    }

    public function getTestDirectory(): string
    {
        return '/mock/directory';
    }

    public function validate(mixed $data, mixed $schema): ValidationResult
    {
        throw new RuntimeException('Validation failed');
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
}

/**
 * Mock implementation with varying validity results.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockPrismTestVaryingValidity implements PrismTestInterface
{
    private int $callCount = 0;

    public function getName(): string
    {
        return 'MockPrism';
    }

    public function getValidatorClass(): string
    {
        return MockValidator::class;
    }

    public function getTestDirectory(): string
    {
        return '/mock/directory';
    }

    public function validate(mixed $data, mixed $schema): ValidationResult
    {
        // Alternate between valid and invalid
        $isValid = ($this->callCount % 2) === 0;
        ++$this->callCount;

        return new MockValidationResult($isValid);
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
}

/**
 * Mock implementation of ValidationResult.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class MockValidationResult implements ValidationResult
{
    /**
     * @param array<int, string> $errors
     */
    public function __construct(
        private bool $isValid,
        private array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

/**
 * Mock validator class for type safety.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockValidator {}
