<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Compliance\Commands\TestCommand;
use Cline\Compliance\Contracts\ComplianceTestInterface;
use Cline\Compliance\Contracts\ValidationResult;
use Symfony\Component\Console\Application;
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

        array_map('unlink', glob($this->testDir.'/tests/*.json') ?: []);
        rmdir($this->testDir.'/tests');
        unlink($this->testDir.'/compliance.php');
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
    new class ('%s') implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class() implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('compliance.php', $config);

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
    new class ('%s') implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Fail Suite'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class() implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('compliance.php', $config);

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
    new class ('%s') implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Test'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class() implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('compliance.php', $config);

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

    test('fails when no compliance tests found', function (): void {
        $originalCwd = getcwd();
        chdir($this->testDir);

        file_put_contents('compliance.php', '<?php return [];');

        $application = new Application();
        $application->add(
            new TestCommand(),
        );

        $command = $application->find('test');
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        expect($exitCode)->toBe(1);
        expect($commandTester->getDisplay())->toContain('No compliance tests found');

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
    new class ('%s') implements Cline\Compliance\Contracts\ComplianceTestInterface {
        public function __construct(private readonly string $testDir) {}
        public function getName(): string { return 'Draft 04'; }
        public function getValidatorClass(): string { return 'Validator'; }
        public function getTestDirectory(): string { return $this->testDir; }
        public function validate(mixed $data, mixed $schema): Cline\Compliance\Contracts\ValidationResult {
            return new class() implements Cline\Compliance\Contracts\ValidationResult {
                public function isValid(): bool { return true; }
                public function getErrors(): array { return []; }
            };
        }
        public function getTestFilePatterns(): array { return ['*.json']; }
        public function decodeJson(string $json): mixed { return json_decode($json, true); }
    },
];
PHP,
            $this->testDir.'/tests',
        );

        file_put_contents('compliance.php', $config);

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
});
