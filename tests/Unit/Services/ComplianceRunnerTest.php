<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Compliance\Contracts\ComplianceTestInterface;
use Cline\Compliance\Contracts\ValidationResult;
use Cline\Compliance\Services\ComplianceRunner;

describe('ComplianceRunner Service', function (): void {
    beforeEach(function (): void {
        $this->testDirectory = sys_get_temp_dir().'/compliance-test-'.uniqid();
        mkdir($this->testDirectory, 0o777, true);
    });

    afterEach(function (): void {
        if (!is_dir($this->testDirectory)) {
            return;
        }

        array_map('unlink', glob($this->testDirectory.'/*.json'));
        rmdir($this->testDirectory);
    });

    test('runs compliance tests successfully', function (): void {
        // Create test file
        $testData = [
            [
                'description' => 'Test group',
                'schema' => ['type' => 'string'],
                'tests' => [
                    [
                        'description' => 'valid string',
                        'data' => 'hello',
                        'valid' => true,
                    ],
                ],
            ],
        ];

        file_put_contents(
            $this->testDirectory.'/test.json',
            json_encode($testData),
        );

        // Mock compliance implementation
        $compliance = new class($this->testDirectory) implements ComplianceTestInterface
        {
            public function __construct(
                private readonly string $testDir,
            ) {}

            public function getName(): string
            {
                return 'Test Suite';
            }

            public function getValidatorClass(): string
            {
                return 'TestValidator';
            }

            public function getTestDirectory(): string
            {
                return $this->testDir;
            }

            public function validate(mixed $data, mixed $schema): ValidationResult
            {
                return new class() implements ValidationResult
                {
                    public function isValid(): bool
                    {
                        return true;
                    }

                    public function getErrors(): array
                    {
                        return [];
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
        };

        $runner = new ComplianceRunner();
        $suite = $runner->run($compliance);

        expect($suite->name)->toBe('Test Suite')
            ->and($suite->totalTests())->toBe(1)
            ->and($suite->passedTests())->toBe(1)
            ->and($suite->failedTests())->toBe(0)
            ->and($suite->duration)->toBeGreaterThan(0.0);
    });

    test('handles validation failures', function (): void {
        $testData = [
            [
                'description' => 'Failure test',
                'schema' => ['type' => 'number'],
                'tests' => [
                    [
                        'description' => 'expects failure',
                        'data' => 'not a number',
                        'valid' => false,
                    ],
                ],
            ],
        ];

        file_put_contents(
            $this->testDirectory.'/fail.json',
            json_encode($testData),
        );

        $compliance = new class($this->testDirectory) implements ComplianceTestInterface
        {
            public function __construct(
                private readonly string $testDir,
            ) {}

            public function getName(): string
            {
                return 'Fail Suite';
            }

            public function getValidatorClass(): string
            {
                return 'TestValidator';
            }

            public function getTestDirectory(): string
            {
                return $this->testDir;
            }

            public function validate(mixed $data, mixed $schema): ValidationResult
            {
                // Simulate validation failure
                return new class() implements ValidationResult
                {
                    public function isValid(): bool
                    {
                        return false;
                    }

                    public function getErrors(): array
                    {
                        return ['Type mismatch'];
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
        };

        $runner = new ComplianceRunner();
        $suite = $runner->run($compliance);

        expect($suite->totalTests())->toBe(1)
            ->and($suite->passedTests())->toBe(1)
            ->and($suite->failedTests())->toBe(0);
    });

    test('handles non-existent test directory', function (): void {
        $compliance = new class() implements ComplianceTestInterface
        {
            public function getName(): string
            {
                return 'No Dir Suite';
            }

            public function getValidatorClass(): string
            {
                return 'TestValidator';
            }

            public function getTestDirectory(): string
            {
                return '/non/existent/directory';
            }

            public function validate(mixed $data, mixed $schema): ValidationResult
            {
                return new class() implements ValidationResult
                {
                    public function isValid(): bool
                    {
                        return true;
                    }

                    public function getErrors(): array
                    {
                        return [];
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
        };

        $runner = new ComplianceRunner();
        $suite = $runner->run($compliance);

        expect($suite->totalTests())->toBe(0)
            ->and($suite->passedTests())->toBe(0)
            ->and($suite->failedTests())->toBe(0);
    });
});
