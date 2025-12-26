<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Application;
use Cline\Prism\Commands\TestCommand;

describe('Application', function (): void {
    beforeEach(function (): void {
        $this->application = new Application();
    });

    describe('initialization', function (): void {
        test('initializes application with correct name', function (): void {
            expect($this->application->getName())->toBe('Prism');
        });

        test('initializes application with correct version', function (): void {
            expect($this->application->getVersion())->toBe('1.0.0');
        });

        test('application has correct long version string', function (): void {
            $longVersion = $this->application->getLongVersion();

            expect($longVersion)->toContain('Prism')
                ->and($longVersion)->toContain('1.0.0');
        });
    });

    describe('command registration', function (): void {
        test('registers TestCommand successfully', function (): void {
            $command = $this->application->get('test');

            expect($command)->toBeInstanceOf(TestCommand::class);
        });

        test('sets test command as default command', function (): void {
            $definition = $this->application->getDefinition();
            $hasCommandArgument = $definition->hasArgument('command');

            // When setDefaultCommand is called with $true, it removes the command argument
            // making it possible to run the default command without specifying it
            expect($hasCommandArgument)->toBeFalse();
        });

        test('contains all expected default commands', function (): void {
            $commands = $this->application->all();

            expect($commands)->toHaveKeys(['test', 'help', 'list', 'completion']);
        });

        test('test command has correct name', function (): void {
            $command = $this->application->get('test');

            expect($command->getName())->toBe('test');
        });

        test('TestCommand is properly configured', function (): void {
            $command = $this->application->get('test');
            $description = $command->getDescription();
            $definition = $command->getDefinition();

            expect($description)->toBe('Run prism tests')
                ->and($definition->hasOption('ci'))->toBeTrue()
                ->and($definition->hasOption('failures'))->toBeTrue()
                ->and($definition->hasOption('draft'))->toBeTrue();
        });
    });

    describe('independence and inheritance', function (): void {
        test('creates independent application instances', function (): void {
            $application1 = new Application();
            $application2 = new Application();

            $name1 = $application1->getName();
            $name2 = $application2->getName();
            $command1 = $application1->get('test');
            $command2 = $application2->get('test');

            expect($name1)->toBe($name2)
                ->and($application1)->not->toBe($application2)
                ->and($command1)->not->toBe($command2);
        });

        test('inherits Symfony Application helper set', function (): void {
            $helperSet = $this->application->getHelperSet();

            expect($helperSet)->not->toBeNull()
                ->and($helperSet->has('formatter'))->toBeTrue()
                ->and($helperSet->has('question'))->toBeTrue();
        });

        test('inherits Symfony Application namespace functionality', function (): void {
            $namespaces = $this->application->getNamespaces();

            expect($namespaces)->toBeArray()
                ->and($namespaces)->toHaveCount(0);
        });
    });
});
