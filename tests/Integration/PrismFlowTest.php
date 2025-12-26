<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Services\ConfigLoader;
use Cline\Prism\Services\PrismRunner;

describe('Full Prism Flow', function (): void {
    beforeEach(function (): void {
        $this->workDir = sys_get_temp_dir().'/prism-flow-'.uniqid();
        mkdir($this->workDir, 0o777, true);
        mkdir($this->workDir.'/tests', 0o777, true);
    });

    afterEach(function (): void {
        if (!is_dir($this->workDir)) {
            return;
        }

        array_map(unlink(...), glob($this->workDir.'/tests/*.json'));
        rmdir($this->workDir.'/tests');
        unlink($this->workDir.'/prism.php');
        rmdir($this->workDir);
    });

    test('runs complete prism test suite from config', function (): void {
        // Create test files
        $testData = [
            [
                'description' => 'String validation',
                'schema' => ['type' => 'string'],
                'tests' => [
                    ['description' => 'valid string', 'data' => 'hello', 'valid' => true],
                    ['description' => 'invalid number', 'data' => 42, 'valid' => false],
                ],
            ],
            [
                'description' => 'Number validation',
                'schema' => ['type' => 'number'],
                'tests' => [
                    ['description' => 'valid number', 'data' => 42, 'valid' => true],
                    ['description' => 'invalid string', 'data' => 'hello', 'valid' => false],
                ],
            ],
        ];

        file_put_contents(
            $this->workDir.'/tests/validation.json',
            json_encode($testData),
        );

        // Create config file
        $configContent = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private string $testDir) {}

        public function getName(): string { return 'Integration Suite'; }
        public function getValidatorClass(): string { return 'TestValidator'; }
        public function getTestDirectory(): string { return $this->testDir; }

        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            $valid = match ($schema['type'] ?? null) {
                'string' => is_string($data),
                'number' => is_numeric($data),
                default => false,
            };

            return new class ($valid) implements Cline\Prism\Contracts\ValidationResult {
                public function __construct(private bool $valid) {}
                public function isValid(): bool { return $this->valid; }
                public function getErrors(): array { return $this->valid ? [] : ['Type mismatch']; }
            };
        }

        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); } public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->workDir.'/tests',
        );

        file_put_contents($this->workDir.'/prism.php', $configContent);

        // Load and run
        $loader = new ConfigLoader();
        $suites = $loader->load($this->workDir.'/prism.php');

        expect($suites)->toHaveCount(1);

        $runner = new PrismRunner();
        $testSuite = $runner->run($suites[0]);

        expect($testSuite->name)->toBe('Integration Suite')
            ->and($testSuite->totalTests())->toBe(4)
            ->and($testSuite->passedTests())->toBe(4)
            ->and($testSuite->failedTests())->toBe(0)
            ->and($testSuite->passRate())->toBe(100.0);
    });

    test('handles multiple test suites', function (): void {
        // Create test files for suite 1
        file_put_contents(
            $this->workDir.'/tests/suite1.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        // Create config with multiple suites
        $configContent = sprintf(
            <<<'PHP'
<?php
$testDir = '%s';
return [
    new class ($testDir, 'Suite A') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private string $testDir, private string $name) {}
        public function getName(): string { return $this->name; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); } public function shouldIncludeFile(string $filePath): bool { return true; }
    },
    new class ($testDir, 'Suite B') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private string $testDir, private string $name) {}
        public function getName(): string { return $this->name; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); } public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->workDir.'/tests',
        );

        file_put_contents($this->workDir.'/prism.php', $configContent);

        $loader = new ConfigLoader();
        $suites = $loader->load($this->workDir.'/prism.php');

        expect($suites)->toHaveCount(2);

        $runner = new PrismRunner();
        $results = array_map($runner->run(...), $suites);

        expect($results[0]->name)->toBe('Suite A')
            ->and($results[1]->name)->toBe('Suite B')
            ->and($results[0]->totalTests())->toBe(1)
            ->and($results[1]->totalTests())->toBe(1);
    });
});
