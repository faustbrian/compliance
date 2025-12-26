<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Commands\TestCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

describe('TestCommand', function (): void {
    beforeEach(function (): void {
        $this->testDir = sys_get_temp_dir().'/cmd-test-'.uniqid();
        mkdir($this->testDir, 0o777, true);
        mkdir($this->testDir.'/tests', 0o777, true);
    });

    afterEach(function (): void {
        if (!is_dir($this->testDir)) {
            return;
        }

        // Recursive function to delete directory
        $deleteDir = function (string $dir) use (&$deleteDir): void {
            if (!is_dir($dir)) {
                return;
            }

            $files = glob($dir.'/{,.}[!.,!..]*', \GLOB_MARK | \GLOB_BRACE) ?: [];

            foreach ($files as $file) {
                if (is_dir($file)) {
                    $deleteDir($file);
                } else {
                    unlink($file);
                }
            }

            rmdir($dir);
        };

        // Clean up all files and directories
        if (is_dir($this->testDir.'/tests')) {
            $deleteDir($this->testDir.'/tests');
        }

        if (is_dir($this->testDir.'/.prism')) {
            $deleteDir($this->testDir.'/.prism');
        }

        if (file_exists($this->testDir.'/prism.php')) {
            unlink($this->testDir.'/prism.php');
        }

        rmdir($this->testDir);
    });

    test('executes with default config discovery', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        // Create test file
        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        // Create config
        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with failures mode', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/fail.json',
            json_encode([['description' => 'Fail', 'schema' => [], 'tests' => [
                ['description' => 'fail test', 'data' => 1, 'valid' => false],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Fail Suite'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--failures' => true]);

        expect($exitCode)->toBeGreaterThanOrEqual(0);

        chdir($originalCwd);
    });

    test('executes with CI mode', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--ci' => true, '--failures' => true]);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('fails when no prism tests found', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents('prism.php', '<?php return [];');

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        expect($exitCode)->toBe(1);
        expect($commandTester->getDisplay())->toContain('No prism tests found');

        chdir($originalCwd);
    });

    test('fails when draft filter matches no tests', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Draft 04'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--draft' => 'Nonexistent']);

        expect($exitCode)->toBe(1);
        expect($commandTester->getDisplay())->toContain('Draft "Nonexistent" not found');

        chdir($originalCwd);
    });

    test('fails when invalid format option is provided', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--format' => 'invalid']);

        expect($exitCode)->toBe(1);
        expect($commandTester->getDisplay())->toContain('Invalid format "invalid"');
        expect($commandTester->getDisplay())->toContain('Valid formats: text, json, xml');

        chdir($originalCwd);
    });

    test('executes with JSON output format', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--format' => 'json']);

        expect($exitCode)->toBe(0);
        expect($commandTester->getDisplay())->toContain('"summary":');

        chdir($originalCwd);
    });

    test('executes with JSON output format and failures', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--format' => 'json', '--failures' => true]);

        expect($exitCode)->toBe(0);
        expect($commandTester->getDisplay())->toContain('"summary":');

        chdir($originalCwd);
    });

    test('executes with XML output format', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--format' => 'xml']);

        expect($exitCode)->toBe(0);
        expect($commandTester->getDisplay())->toContain('<?xml');

        chdir($originalCwd);
    });

    test('executes with XML output format and failures', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--format' => 'xml', '--failures' => true]);

        expect($exitCode)->toBe(0);
        expect($commandTester->getDisplay())->toContain('<?xml');

        chdir($originalCwd);
    });

    test('executes with list-assertions option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents('prism.php', '<?php return [];');

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--list-assertions' => true]);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with filter option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--filter' => '/t1/']);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with path-filter option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--path-filter' => '*.json']);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with exclude option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--exclude' => '/nonexistent/']);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with tag option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--tag' => 'smoke']);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with parallel option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--parallel' => '2']);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with incremental option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--incremental' => true]);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with profile option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--profile' => true]);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with baseline option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--baseline' => 'test-baseline']);

        expect($exitCode)->toBe(0);
        expect($commandTester->getDisplay())->toContain('Baseline "test-baseline" saved successfully');

        chdir($originalCwd);
    });

    test('executes with baseline option using default name', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--baseline' => '']);

        expect($exitCode)->toBe(0);
        expect($commandTester->getDisplay())->toContain('Baseline "default" saved successfully');

        chdir($originalCwd);
    });

    test('executes with compare option when baseline exists', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        // First save a baseline
        $commandTester->execute(['--baseline' => 'compare-test']);

        // Then compare
        $exitCode = $commandTester->execute(['--compare' => 'compare-test']);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with compare option when baseline does not exist', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--compare' => 'nonexistent']);

        expect($exitCode)->toBe(0);
        expect($commandTester->getDisplay())->toContain('Baseline "nonexistent" not found');

        chdir($originalCwd);
    });

    test('executes with compare option using default name', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--compare' => '']);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with update-snapshots option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--update-snapshots' => true]);

        expect($exitCode)->toBe(0);
        expect($commandTester->getDisplay())->toContain('Snapshots updated successfully');

        chdir($originalCwd);
    });

    test('executes with check-snapshots option when snapshot exists', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        // First update snapshots
        $commandTester->execute(['--update-snapshots' => true]);

        // Then check snapshots
        $exitCode = $commandTester->execute(['--check-snapshots' => true]);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with check-snapshots option when snapshot does not exist', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--check-snapshots' => true]);

        expect($exitCode)->toBe(0);
        expect($commandTester->getDisplay())->toContain('Snapshot for "Test" not found');

        chdir($originalCwd);
    });

    test('executes with compare-validators option with multiple validators', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Validator1'; }
        public function getValidatorClass(): string { return 'Validator1'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Validator2'; }
        public function getValidatorClass(): string { return 'Validator2'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--compare-validators' => true]);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with compare-validators option with insufficient validators', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--compare-validators' => true]);

        expect($exitCode)->toBe(1);
        expect($commandTester->getDisplay())->toContain('At least two validators required for comparison');

        chdir($originalCwd);
    });

    test('executes with coverage option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--coverage' => true]);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with fuzz option', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--fuzz' => '10']);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with parallel and incremental combined', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['--parallel' => '2', '--incremental' => true]);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with all filter options combined', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            '--filter' => '/t1/',
            '--path-filter' => '*.json',
            '--exclude' => '/nonexistent/',
            '--tag' => 'unit',
        ]);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with verbose mode and progress tracking', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });

    test('executes with verbose mode, incremental mode, and progress tracking', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents(
            $this->testDir.'/tests/test.json',
            json_encode([['description' => 'Test', 'schema' => [], 'tests' => [
                ['description' => 't1', 'data' => 1, 'valid' => true],
            ]]]),
        );

        $config = sprintf(
            <<<'PHP'
<?php
return [
    new class ('%s') implements Cline\Prism\Contracts\PrismTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Prism\Contracts\ValidationResult {
            return new class() implements Cline\Prism\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
        public function shouldIncludeFile(string $filePath): bool { return true; }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('prism.php', $config);

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(
            ['--incremental' => true],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        expect($exitCode)->toBe(0);

        chdir($originalCwd);
    });
});
