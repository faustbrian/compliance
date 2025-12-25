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
use Cline\Compliance\ValueObjects\TestResult;
use Cline\Compliance\ValueObjects\TestSuite;

describe('ComplianceRunner', function (): void {
    beforeEach(function (): void {
        $this->testDir = sys_get_temp_dir().'/compliance-test-'.uniqid();
        mkdir($this->testDir, 0o777, recursive: true);
        $this->runner = new ComplianceRunner();
    });

    afterEach(function (): void {
        // Clean up test directory recursively
        if (!is_dir($this->testDir)) {
            return;
        }

        $removeDir = function (string $dir) use (&$removeDir): void {
            if (!is_dir($dir)) {
                return;
            }

            $items = scandir($dir) ?: [];

            foreach ($items as $item) {
                if ($item === '.') {
                    continue;
                }
                if ($item === '..') {
                    continue;
                }
                $path = $dir.'/'.$item;

                if (is_dir($path)) {
                    $removeDir($path);
                } else {
                    chmod($path, 0o644);
                    unlink($path);
                }
            }

            rmdir($dir);
        };

        $removeDir($this->testDir);
    });

    describe('run() - Happy Paths', function (): void {
        test('executes compliance tests successfully with passing results', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'Valid test group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Valid string',
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/test.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->name)->toBe('TestSuite')
                ->and($result->totalTests())->toBe(1)
                ->and($result->passedTests())->toBe(1)
                ->and($result->failedTests())->toBe(0)
                ->and($result->duration)->toBeGreaterThan(0.0);
        });

        test('executes compliance tests with failing results', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'Invalid test group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Invalid number',
                            'data' => 123,
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/test.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                            return false;
                        }

                        public function getErrors(): array
                        {
                            return ['Invalid type'];
                        }
                    };
                }

                public function getTestFilePatterns(): array
                {
                    return ['*.json'];
                }

                public function decodeJson(string $json): mixed
                {
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->totalTests())->toBe(1)
                ->and($result->passedTests())->toBe(0)
                ->and($result->failedTests())->toBe(1)
                ->and($result->failures())->toHaveCount(1);
        });

        test('processes multiple test files in sorted order', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'Test group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Test',
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/z-last.json', $testContent);
            file_put_contents($this->testDir.'/a-first.json', $testContent);
            file_put_contents($this->testDir.'/m-middle.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->totalTests())->toBe(3)
                ->and($result->results[0]->file)->toContain('a-first')
                ->and($result->results[1]->file)->toContain('m-middle')
                ->and($result->results[2]->file)->toContain('z-last');
        });
    });

    describe('run() - Non-existent Directory', function (): void {
        test('returns empty test suite when test directory does not exist', function (): void {
            // Arrange
            $compliance = new class() implements ComplianceTestInterface
            {
                public function getName(): string
                {
                    return 'TestSuite';
                }

                public function getValidatorClass(): string
                {
                    return 'TestValidator';
                }

                public function getTestDirectory(): string
                {
                    return '/non/existent/directory/path';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->totalTests())->toBe(0)
                ->and($result->results)->toBeEmpty();
        });
    });

    describe('run() - File Filtering', function (): void {
        test('excludes files when shouldIncludeFile returns false', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'Test group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Test',
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/included.json', $testContent);
            file_put_contents($this->testDir.'/excluded.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return !str_contains($filePath, 'excluded');
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->totalTests())->toBe(1);
        });

        test('skips non-JSON files during collection', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'Test group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Test',
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/valid.json', $testContent);
            file_put_contents($this->testDir.'/invalid.txt', 'not json');
            file_put_contents($this->testDir.'/invalid.php', '<?php echo "test";');

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->totalTests())->toBe(1);
        });
    });

    describe('run() - File Read Failures', function (): void {
        test('returns empty results when file_get_contents returns false', function (): void {
            // Arrange
            // Create a file and then make it unreadable
            file_put_contents($this->testDir.'/unreadable.json', 'test');
            chmod($this->testDir.'/unreadable.json', 0o000);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Cleanup before assertions
            chmod($this->testDir.'/unreadable.json', 0o644);

            // Assert
            expect($result->totalTests())->toBe(0)
                ->and($result->results)->toBeEmpty();
        });
    });

    describe('run() - Invalid JSON Structures', function (): void {
        test('returns empty results when decodeJson returns non-array', function (): void {
            // Arrange
            file_put_contents($this->testDir.'/invalid.json', '"string value"');

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->totalTests())->toBe(0);
        });

        test('skips non-array groups in testGroups', function (): void {
            // Arrange
            $testContent = json_encode([
                'invalid-string-group',
                123,
                [
                    'description' => 'Valid group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Valid test',
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
                null,
            ]);

            file_put_contents($this->testDir.'/mixed.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->totalTests())->toBe(1);
        });

        test('skips groups with non-array tests', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'Group with invalid tests',
                    'schema' => ['type' => 'string'],
                    'tests' => 'not-an-array',
                ],
                [
                    'description' => 'Valid group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Valid test',
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/test.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->totalTests())->toBe(1);
        });

        test('skips non-array tests within tests array', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'Group with mixed tests',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        'invalid-string',
                        123,
                        [
                            'description' => 'Valid test',
                            'data' => 'test',
                            'valid' => true,
                        ],
                        null,
                        [
                            'description' => 'Another valid test',
                            'data' => 'test2',
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/test.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->totalTests())->toBe(2);
        });
    });

    describe('run() - Exception Handling', function (): void {
        test('captures exceptions thrown during validation', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'Test group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Test that throws',
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/test.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    throw new RuntimeException('Validation error occurred');
                }

                public function getTestFilePatterns(): array
                {
                    return ['*.json'];
                }

                public function decodeJson(string $json): mixed
                {
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->totalTests())->toBe(1)
                ->and($result->failedTests())->toBe(1)
                ->and($result->failures()[0]->error)->toBe('Validation error occurred')
                ->and($result->failures()[0]->actualValid)->toBe(false);
        });

        test('creates test result with error message when exception occurs', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'Exception test group',
                    'schema' => ['type' => 'object'],
                    'tests' => [
                        [
                            'description' => 'Test with custom exception',
                            'data' => ['key' => 'value'],
                            'valid' => false,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/exception.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'ExceptionSuite';
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
                    throw new InvalidArgumentException('Custom validation exception');
                }

                public function getTestFilePatterns(): array
                {
                    return ['*.json'];
                }

                public function decodeJson(string $json): mixed
                {
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            $failure = $result->failures()[0];

            expect($failure)->toBeInstanceOf(TestResult::class)
                ->and($failure->passed)->toBe(false)
                ->and($failure->expectedValid)->toBe(false)
                ->and($failure->actualValid)->toBe(false)
                ->and($failure->error)->toBe('Custom validation exception')
                ->and($failure->id)->toContain('ExceptionSuite:exception:0:0');
        });
    });

    describe('run() - Test Metadata', function (): void {
        test('generates correct test IDs with file, group, and test indices', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'First group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'First test',
                            'data' => 'test1',
                            'valid' => true,
                        ],
                        [
                            'description' => 'Second test',
                            'data' => 'test2',
                            'valid' => true,
                        ],
                    ],
                ],
                [
                    'description' => 'Second group',
                    'schema' => ['type' => 'number'],
                    'tests' => [
                        [
                            'description' => 'Third test',
                            'data' => 123,
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/metadata.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'MetadataSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->results[0]->id)->toBe('MetadataSuite:metadata:0:0')
                ->and($result->results[1]->id)->toBe('MetadataSuite:metadata:0:1')
                ->and($result->results[2]->id)->toBe('MetadataSuite:metadata:1:0');
        });

        test('uses default descriptions when group description is missing or invalid', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Test',
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
                [
                    'description' => 123,
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Test',
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/test.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->results[0]->group)->toBe('Unknown group')
                ->and($result->results[1]->group)->toBe('Unknown group');
        });

        test('uses default descriptions when test description is missing or invalid', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'Test group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'data' => 'test',
                            'valid' => true,
                        ],
                        [
                            'description' => ['array' => 'not string'],
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/test.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->results[0]->description)->toBe('Unknown test')
                ->and($result->results[1]->description)->toBe('Unknown test');
        });

        test('correctly determines expectedValid from test valid field', function (): void {
            // Arrange
            $testContent = json_encode([
                [
                    'description' => 'Test group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Valid true',
                            'data' => 'test',
                            'valid' => true,
                        ],
                        [
                            'description' => 'Valid false',
                            'data' => 'test',
                            'valid' => false,
                        ],
                        [
                            'description' => 'Valid missing',
                            'data' => 'test',
                        ],
                        [
                            'description' => 'Valid non-boolean',
                            'data' => 'test',
                            'valid' => 'true',
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/test.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->results[0]->expectedValid)->toBe(true)
                ->and($result->results[1]->expectedValid)->toBe(false)
                ->and($result->results[2]->expectedValid)->toBe(false)
                ->and($result->results[3]->expectedValid)->toBe(false);
        });
    });

    describe('run() - Edge Cases', function (): void {
        test('handles empty test directory', function (): void {
            // Arrange
            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->totalTests())->toBe(0)
                ->and($result->results)->toBeEmpty();
        });

        test('handles test file with empty array', function (): void {
            // Arrange
            file_put_contents($this->testDir.'/empty.json', '[]');

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->totalTests())->toBe(0);
        });

        test('processes recursive directory structures', function (): void {
            // Arrange
            mkdir($this->testDir.'/subdir1', 0o777, recursive: true);
            mkdir($this->testDir.'/subdir2/nested', 0o777, recursive: true);

            $testContent = json_encode([
                [
                    'description' => 'Test group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Test',
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
            ]);

            file_put_contents($this->testDir.'/root.json', $testContent);
            file_put_contents($this->testDir.'/subdir1/sub1.json', $testContent);
            file_put_contents($this->testDir.'/subdir2/sub2.json', $testContent);
            file_put_contents($this->testDir.'/subdir2/nested/nested.json', $testContent);

            $compliance = new readonly class($this->testDir) implements ComplianceTestInterface
            {
                public function __construct(
                    private string $testDir,
                ) {}

                public function getName(): string
                {
                    return 'TestSuite';
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
                    return json_decode($json, associative: true);
                }

                public function shouldIncludeFile(string $filePath): bool
                {
                    return true;
                }
            };

            // Act
            $result = $this->runner->run($compliance);

            // Assert
            expect($result->totalTests())->toBe(4);
        });
    });
});
