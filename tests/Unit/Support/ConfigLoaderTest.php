<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Compliance\Contracts\ComplianceTestInterface;
use Cline\Compliance\Services\ConfigLoader;

describe('ConfigLoader', function (): void {
    beforeEach(function (): void {
        $this->testConfigDir = sys_get_temp_dir().'/config-test-'.uniqid();
        mkdir($this->testConfigDir, 0o777, true);
    });

    afterEach(function (): void {
        if (!is_dir($this->testConfigDir)) {
            return;
        }

        array_map(unlink(...), glob($this->testConfigDir.'/*.php'));
        rmdir($this->testConfigDir);
    });

    test('loads compliance configuration from default path', function (): void {
        // Create a test config file
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
        $suites = $loader->load($configPath);

        expect($suites)->toBeArray()
            ->toHaveCount(1)
            ->and($suites[0])->toBeInstanceOf(ComplianceTestInterface::class);
    });

    test('throws exception when config file does not exist', function (): void {
        $loader = new ConfigLoader();

        expect(fn (): array => $loader->load('/non/existent/config.php'))
            ->toThrow(RuntimeException::class, 'Compliance configuration file not found');
    });

    test('throws exception when config returns non-array', function (): void {
        $configPath = $this->testConfigDir.'/invalid.php';
        file_put_contents($configPath, '<?php return "invalid";');

        $loader = new ConfigLoader();

        expect(fn (): array => $loader->load($configPath))
            ->toThrow(RuntimeException::class, 'Compliance configuration must return an array');
    });

    test('discovers config in current directory', function (): void {
        $originalCwd = getcwd();
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
        $suites = $loader->load();

        expect($suites)->toBeArray()
            ->toHaveCount(1);

        chdir($originalCwd);
    });
});
