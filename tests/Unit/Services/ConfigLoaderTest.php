<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Compliance\Contracts\ComplianceTestInterface;
use Cline\Compliance\Exceptions\ConfigurationFileNotFoundException;
use Cline\Compliance\Exceptions\ConfigurationMustReturnArrayException;
use Cline\Compliance\Services\ConfigLoader;

describe('ConfigLoader', function (): void {
    beforeEach(function (): void {
        $this->testConfigDir = sys_get_temp_dir().'/config-test-'.uniqid();
        mkdir($this->testConfigDir, 0o777, recursive: true);
        $this->originalCwd = getcwd();
    });

    afterEach(function (): void {
        // Restore original working directory
        chdir($this->originalCwd);

        // Clean up test directory
        if (!is_dir($this->testConfigDir)) {
            return;
        }

        // Remove all PHP files
        $files = glob($this->testConfigDir.'/*.php') ?: [];
        array_map(unlink(...), $files);

        // Remove config subdirectory if it exists
        $configDir = $this->testConfigDir.'/config';

        if (is_dir($configDir)) {
            $configFiles = glob($configDir.'/*.php') ?: [];
            array_map(unlink(...), $configFiles);
            rmdir($configDir);
        }

        rmdir($this->testConfigDir);
    });

    describe('load() with explicit path', function (): void {
        test('loads compliance configuration from explicit path', function (): void {
            // Arrange
            $configPath = $this->testConfigDir.'/compliance.php';
            $configContent = <<<'PHP'
<?php
return [
    new class implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return '/tmp'; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP;

            file_put_contents($configPath, $configContent);
            $loader = new ConfigLoader();

            // Act
            $suites = $loader->load($configPath);

            // Assert
            expect($suites)->toBeArray()
                ->toHaveCount(1)
                ->and($suites[0])->toBeInstanceOf(ComplianceTestInterface::class)
                ->and($suites[0]->getName())->toBe('Test');
        });

        test('throws exception when explicit config file does not exist', function (): void {
            // Arrange
            $loader = new ConfigLoader();
            $nonExistentPath = '/non/existent/config.php';

            // Act & Assert
            expect(fn (): array => $loader->load($nonExistentPath))
                ->toThrow(ConfigurationFileNotFoundException::class);
        });

        test('throws exception when explicit config returns non-array', function (): void {
            // Arrange
            $configPath = $this->testConfigDir.'/invalid.php';
            file_put_contents($configPath, '<?php return "invalid";');
            $loader = new ConfigLoader();

            // Act & Assert
            expect(fn (): array => $loader->load($configPath))
                ->toThrow(ConfigurationMustReturnArrayException::class);
        });

        test('filters out non-ComplianceTestInterface items from explicit config', function (): void {
            // Arrange
            $configPath = $this->testConfigDir.'/mixed.php';
            $configContent = <<<'PHP'
<?php
return [
    'invalid-string',
    123,
    new class implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function getName(): string { return 'Valid'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return '/tmp'; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
    null,
    new stdClass(),
];
PHP;

            file_put_contents($configPath, $configContent);
            $loader = new ConfigLoader();

            // Act
            $suites = $loader->load($configPath);

            // Assert
            expect($suites)->toBeArray()
                ->toHaveCount(1)
                ->and($suites[0])->toBeInstanceOf(ComplianceTestInterface::class);
        });

        test('returns empty array when explicit config array contains no valid items', function (): void {
            // Arrange
            $configPath = $this->testConfigDir.'/all-invalid.php';
            $configContent = <<<'PHP'
<?php
return [
    'invalid-string',
    123,
    null,
    new stdClass(),
];
PHP;

            file_put_contents($configPath, $configContent);
            $loader = new ConfigLoader();

            // Act
            $suites = $loader->load($configPath);

            // Assert
            expect($suites)->toBeArray()->toBeEmpty();
        });

        test('reindexes array after filtering invalid items', function (): void {
            // Arrange
            $configPath = $this->testConfigDir.'/reindex.php';
            $configContent = <<<'PHP'
<?php
return [
    'invalid',
    new class implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function getName(): string { return 'First'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return '/tmp'; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
    'another-invalid',
    new class implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function getName(): string { return 'Second'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return '/tmp'; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP;

            file_put_contents($configPath, $configContent);
            $loader = new ConfigLoader();

            // Act
            $suites = $loader->load($configPath);

            // Assert
            expect($suites)->toHaveCount(2)
                ->and(array_keys($suites))->toBe([0, 1])
                ->and($suites[0]->getName())->toBe('First')
                ->and($suites[1]->getName())->toBe('Second');
        });
    });

    describe('load() with auto-discovery', function (): void {
        test('discovers config in project root (compliance.php)', function (): void {
            // Arrange
            chdir($this->testConfigDir);
            $configContent = <<<'PHP'
<?php
return [
    new class implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function getName(): string { return 'Discovered'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return '/tmp'; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP;

            file_put_contents('compliance.php', $configContent);
            $loader = new ConfigLoader();

            // Act
            $suites = $loader->load();

            // Assert
            expect($suites)->toBeArray()
                ->toHaveCount(1)
                ->and($suites[0]->getName())->toBe('Discovered');
        });

        test('discovers config in config directory (config/compliance.php)', function (): void {
            // Arrange
            chdir($this->testConfigDir);
            mkdir('config');
            $configContent = <<<'PHP'
<?php
return [
    new class implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function getName(): string { return 'ConfigDir'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return '/tmp'; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP;

            file_put_contents('config/compliance.php', $configContent);
            $loader = new ConfigLoader();

            // Act
            $suites = $loader->load();

            // Assert
            expect($suites)->toBeArray()
                ->toHaveCount(1)
                ->and($suites[0]->getName())->toBe('ConfigDir');
        });

        test('prioritizes root compliance.php over config/compliance.php', function (): void {
            // Arrange
            chdir($this->testConfigDir);
            mkdir('config');

            $rootConfigContent = <<<'PHP'
<?php
return [
    new class implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function getName(): string { return 'Root'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return '/tmp'; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP;

            $configDirContent = <<<'PHP'
<?php
return [
    new class implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function getName(): string { return 'ConfigDir'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return '/tmp'; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP;

            file_put_contents('compliance.php', $rootConfigContent);
            file_put_contents('config/compliance.php', $configDirContent);
            $loader = new ConfigLoader();

            // Act
            $suites = $loader->load();

            // Assert
            expect($suites)->toBeArray()
                ->toHaveCount(1)
                ->and($suites[0]->getName())->toBe('Root');
        });

        test('returns empty array when auto-discovered config returns non-array', function (): void {
            // Arrange
            chdir($this->testConfigDir);
            file_put_contents('compliance.php', '<?php return "invalid";');
            $loader = new ConfigLoader();

            // Act
            $suites = $loader->load();

            // Assert
            expect($suites)->toBeArray()->toBeEmpty();
        });

        test('returns empty array when no config file found', function (): void {
            // Arrange
            chdir($this->testConfigDir);
            $loader = new ConfigLoader();

            // Act
            $suites = $loader->load();

            // Assert
            expect($suites)->toBeArray()->toBeEmpty();
        });

        test('filters out invalid items from auto-discovered config', function (): void {
            // Arrange
            chdir($this->testConfigDir);
            $configContent = <<<'PHP'
<?php
return [
    'invalid-string',
    new class implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function getName(): string { return 'Valid'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return '/tmp'; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
    null,
];
PHP;

            file_put_contents('compliance.php', $configContent);
            $loader = new ConfigLoader();

            // Act
            $suites = $loader->load();

            // Assert
            expect($suites)->toBeArray()
                ->toHaveCount(1)
                ->and($suites[0])->toBeInstanceOf(ComplianceTestInterface::class);
        });

        test('returns empty array when auto-discovered config contains no valid items', function (): void {
            // Arrange
            chdir($this->testConfigDir);
            $configContent = <<<'PHP'
<?php
return [
    'invalid',
    123,
    null,
];
PHP;

            file_put_contents('compliance.php', $configContent);
            $loader = new ConfigLoader();

            // Act
            $suites = $loader->load();

            // Assert
            expect($suites)->toBeArray()->toBeEmpty();
        });
    });
});
