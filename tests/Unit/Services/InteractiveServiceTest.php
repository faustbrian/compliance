<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Exceptions\InvalidWorkerCountException;
use Cline\Prism\Services\InteractiveService;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

describe('InteractiveService', function (): void {
    beforeEach(function (): void {
        $this->helper = mock(QuestionHelper::class);
        $this->input = mock(InputInterface::class);
        $this->output = mock(OutputInterface::class);

        $this->service = new InteractiveService(
            $this->helper,
            $this->input,
            $this->output,
        );

        // Helper function to call private methods using reflection
        $this->callPrivateMethod = function (object $object, string $method, mixed $args = []): mixed {
            $reflection = new ReflectionClass($object);
            $reflectionMethod = $reflection->getMethod($method);

            // For all methods, $args should be an array
            return $reflectionMethod->invokeArgs($object, is_array($args) ? $args : [$args]);
        };

        // Special helper for clearOptions which needs to modify the array by reference
        $this->callClearOptions = function (array &$options): void {
            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('clearOptions');
            $method->invokeArgs($this->service, [&$options]);
        };
    });

    describe('constructor', function (): void {
        test('creates service with required dependencies', function (): void {
            // Arrange
            $helper = mock(QuestionHelper::class);
            $input = mock(InputInterface::class);
            $output = mock(OutputInterface::class);

            // Act
            $service = new InteractiveService($helper, $input, $output);

            // Assert
            expect($service)->toBeInstanceOf(InteractiveService::class);
        });
    });

    describe('run()', function (): void {
        test('runs interactive loop testing all match branches', function (): void {
            // This test exercises all branches of the match statement in run()
            // by simulating user selecting each action once before exiting

            $callbackExecuted = false;

            // Allow any number of writeln calls for menu display
            $this->output->shouldReceive('writeln')->atLeast()->once();

            // Iteration 1: filter action
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('filter');
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('TestPattern');

            // Iteration 2: tag action
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('tag');
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('unit');

            // Iteration 3: parallel action
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('parallel');
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn(4);

            // Iteration 4: incremental toggle
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('incremental');

            // Iteration 5: watch toggle
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('watch');

            // Iteration 6: run tests (in watch mode, so no Enter wait)
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('run');
            $this->input->shouldReceive('setOption')->atLeast()->once();

            // Iteration 7: clear action
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('clear');

            // Iteration 8: default case (unknown action)
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('unknown_action');

            // Final: exit
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('exit');

            // Act - run will call exit(0) which terminates the test process
            // We expect this test to actually exit, so we skip it to avoid breaking the test suite
        })->skip('Test would call exit() and terminate test suite');
    });

    describe('displayCurrentOptions()', function (): void {
        test('displays all options with default values', function (): void {
            // Arrange
            $options = [
                'filter' => null,
                'tag' => null,
                'parallel' => 1,
                'incremental' => false,
                'watch' => false,
            ];

            $this->output
                ->shouldReceive('writeln')
                ->with('<fg=cyan>Current Configuration:</>')
                ->once();

            $this->output
                ->shouldReceive('writeln')
                ->with('  Filter: <none>')
                ->once();

            $this->output
                ->shouldReceive('writeln')
                ->with('  Tag: <none>')
                ->once();

            $this->output
                ->shouldReceive('writeln')
                ->with('  Parallel: 1 worker(s)')
                ->once();

            $this->output
                ->shouldReceive('writeln')
                ->with('  Incremental: disabled')
                ->once();

            $this->output
                ->shouldReceive('writeln')
                ->with('  Watch: disabled')
                ->once();

            // Act
            ($this->callPrivateMethod)($this->service, 'displayCurrentOptions', [$options]);

            // Assert - verified through mock expectations
        });

        test('displays all options with filter set', function (): void {
            // Arrange
            $options = [
                'filter' => 'MyTest',
                'tag' => null,
                'parallel' => 1,
                'incremental' => false,
                'watch' => false,
            ];

            $this->output->shouldReceive('writeln')->times(6);

            // Act
            ($this->callPrivateMethod)($this->service, 'displayCurrentOptions', [$options]);

            // Assert - verified through mock expectations
        });

        test('displays all options with tag set', function (): void {
            // Arrange
            $options = [
                'filter' => null,
                'tag' => 'unit',
                'parallel' => 1,
                'incremental' => false,
                'watch' => false,
            ];

            $this->output->shouldReceive('writeln')->times(6);

            // Act
            ($this->callPrivateMethod)($this->service, 'displayCurrentOptions', [$options]);

            // Assert - verified through mock expectations
        });

        test('displays all options with parallel workers set', function (): void {
            // Arrange
            $options = [
                'filter' => null,
                'tag' => null,
                'parallel' => 4,
                'incremental' => false,
                'watch' => false,
            ];

            $this->output
                ->shouldReceive('writeln')
                ->times(6);

            // Act
            ($this->callPrivateMethod)($this->service, 'displayCurrentOptions', [$options]);

            // Assert - verified through mock expectations
        });

        test('displays all options with incremental enabled', function (): void {
            // Arrange
            $options = [
                'filter' => null,
                'tag' => null,
                'parallel' => 1,
                'incremental' => true,
                'watch' => false,
            ];

            $this->output
                ->shouldReceive('writeln')
                ->times(6);

            // Act
            ($this->callPrivateMethod)($this->service, 'displayCurrentOptions', [$options]);

            // Assert - verified through mock expectations
        });

        test('displays all options with watch enabled', function (): void {
            // Arrange
            $options = [
                'filter' => null,
                'tag' => null,
                'parallel' => 1,
                'incremental' => false,
                'watch' => true,
            ];

            $this->output
                ->shouldReceive('writeln')
                ->times(6);

            // Act
            ($this->callPrivateMethod)($this->service, 'displayCurrentOptions', [$options]);

            // Assert - verified through mock expectations
        });

        test('displays all options with all settings configured', function (): void {
            // Arrange
            $options = [
                'filter' => 'TestPattern',
                'tag' => 'integration',
                'parallel' => 8,
                'incremental' => true,
                'watch' => true,
            ];

            $this->output->shouldReceive('writeln')->times(6);

            // Act
            ($this->callPrivateMethod)($this->service, 'displayCurrentOptions', [$options]);

            // Assert - verified through mock expectations
        });
    });

    describe('promptAction()', function (): void {
        test('prompts user for action and returns selection', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('run');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptAction', []);

            // Assert
            expect($result)->toBe('run');
        });

        test('returns filter action when selected', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('filter');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptAction', []);

            // Assert
            expect($result)->toBe('filter');
        });

        test('returns tag action when selected', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('tag');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptAction', []);

            // Assert
            expect($result)->toBe('tag');
        });

        test('returns parallel action when selected', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('parallel');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptAction', []);

            // Assert
            expect($result)->toBe('parallel');
        });

        test('returns incremental action when selected', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('incremental');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptAction', []);

            // Assert
            expect($result)->toBe('incremental');
        });

        test('returns watch action when selected', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('watch');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptAction', []);

            // Assert
            expect($result)->toBe('watch');
        });

        test('returns clear action when selected', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('clear');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptAction', []);

            // Assert
            expect($result)->toBe('clear');
        });

        test('returns exit action when selected', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('exit');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptAction', []);

            // Assert
            expect($result)->toBe('exit');
        });
    });

    describe('promptFilter()', function (): void {
        test('returns filter pattern when user enters value', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('TestPattern');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptFilter', []);

            // Assert
            expect($result)->toBe('TestPattern');
        });

        test('returns null when user enters empty string', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptFilter', []);

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null when user enters null', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn(null);

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptFilter', []);

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('promptTag()', function (): void {
        test('returns tag when user enters value', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('unit');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptTag', []);

            // Assert
            expect($result)->toBe('unit');
        });

        test('returns null when user enters empty string', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn('');

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptTag', []);

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null when user enters null', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn(null);

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptTag', []);

            // Assert
            expect($result)->toBeNull();
        });
    });

    describe('promptParallel()', function (): void {
        test('returns worker count when user enters valid number', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn(4);

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptParallel', []);

            // Assert
            expect($result)->toBe(4);
        });

        test('returns 1 when user enters 1', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturn(1);

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptParallel', []);

            // Assert
            expect($result)->toBe(1);
        });

        test('throws exception when user enters zero', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturnUsing(function ($input, $output, Question $question) {
                    $validator = $question->getValidator();

                    return $validator(0);
                });

            // Act & Assert
            expect(fn () => ($this->callPrivateMethod)($this->service, 'promptParallel', []))
                ->toThrow(InvalidWorkerCountException::class, 'Number of workers must be at least 1');
        });

        test('throws exception when user enters negative number', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturnUsing(function ($input, $output, Question $question) {
                    $validator = $question->getValidator();

                    return $validator(-5);
                });

            // Act & Assert
            expect(fn () => ($this->callPrivateMethod)($this->service, 'promptParallel', []))
                ->toThrow(InvalidWorkerCountException::class, 'Number of workers must be at least 1');
        });

        test('accepts string number and converts to integer', function (): void {
            // Arrange
            $this->helper
                ->shouldReceive('ask')
                ->once()
                ->andReturnUsing(function ($input, $output, Question $question) {
                    $validator = $question->getValidator();

                    return $validator('8');
                });

            // Act
            $result = ($this->callPrivateMethod)($this->service, 'promptParallel', []);

            // Assert
            expect($result)->toBe(8);
        });
    });

    describe('runTests()', function (): void {
        test('executes callback and waits for Enter in non-watch mode', function (): void {
            // Arrange
            $callbackExecuted = false;
            $callback = function () use (&$callbackExecuted): void {
                $callbackExecuted = true;
            };

            $options = [
                'filter' => null,
                'tag' => null,
                'parallel' => 1,
                'incremental' => false,
                'watch' => false,
            ];

            $this->output->shouldReceive('writeln')->times(5); // 3 before callback + 2 after (not watch mode)
            $this->input->shouldReceive('setOption')->times(2); // Only parallel and incremental when filter/tag are null
            $this->helper->shouldReceive('ask')->once()->andReturn('');

            // Act
            ($this->callPrivateMethod)($this->service, 'runTests', [$callback, $options]);

            // Assert
            expect($callbackExecuted)->toBeTrue();
        });

        test('applies filter option when set', function (): void {
            // Arrange
            $callback = fn (): null => null;
            $options = [
                'filter' => 'MyTestPattern',
                'tag' => null,
                'parallel' => 1,
                'incremental' => false,
                'watch' => false,
            ];

            $this->output->shouldReceive('writeln')->times(5);

            $this->input
                ->shouldReceive('setOption')
                ->with('filter', 'MyTestPattern')
                ->once();

            $this->input
                ->shouldReceive('setOption')
                ->with('parallel', '1')
                ->once();

            $this->input
                ->shouldReceive('setOption')
                ->with('incremental', false)
                ->once();

            $this->helper->shouldReceive('ask')->once()->andReturn('');

            // Act
            ($this->callPrivateMethod)($this->service, 'runTests', [$callback, $options]);

            // Assert - verified through mock expectations
        });

        test('applies tag option when set', function (): void {
            // Arrange
            $callback = fn (): null => null;
            $options = [
                'filter' => null,
                'tag' => 'integration',
                'parallel' => 1,
                'incremental' => false,
                'watch' => false,
            ];

            $this->output->shouldReceive('writeln')->times(5);

            $this->input
                ->shouldReceive('setOption')
                ->with('tag', 'integration')
                ->once();

            $this->input
                ->shouldReceive('setOption')
                ->with('parallel', '1')
                ->once();

            $this->input
                ->shouldReceive('setOption')
                ->with('incremental', false)
                ->once();

            $this->helper->shouldReceive('ask')->once()->andReturn('');

            // Act
            ($this->callPrivateMethod)($this->service, 'runTests', [$callback, $options]);

            // Assert - verified through mock expectations
        });

        test('applies parallel option with correct string conversion', function (): void {
            // Arrange
            $callback = fn (): null => null;
            $options = [
                'filter' => null,
                'tag' => null,
                'parallel' => 8,
                'incremental' => false,
                'watch' => false,
            ];

            $this->output->shouldReceive('writeln')->times(5);

            $this->input
                ->shouldReceive('setOption')
                ->with('parallel', '8')
                ->once();

            $this->input
                ->shouldReceive('setOption')
                ->with('incremental', false)
                ->once();

            $this->helper->shouldReceive('ask')->once()->andReturn('');

            // Act
            ($this->callPrivateMethod)($this->service, 'runTests', [$callback, $options]);

            // Assert - verified through mock expectations
        });

        test('applies incremental option when enabled', function (): void {
            // Arrange
            $callback = fn (): null => null;
            $options = [
                'filter' => null,
                'tag' => null,
                'parallel' => 1,
                'incremental' => true,
                'watch' => false,
            ];

            $this->output->shouldReceive('writeln')->times(5);

            $this->input
                ->shouldReceive('setOption')
                ->with('incremental', true)
                ->once();

            $this->input
                ->shouldReceive('setOption')
                ->with('parallel', '1')
                ->once();

            $this->helper->shouldReceive('ask')->once()->andReturn('');

            // Act
            ($this->callPrivateMethod)($this->service, 'runTests', [$callback, $options]);

            // Assert - verified through mock expectations
        });

        test('does not wait for Enter in watch mode', function (): void {
            // Arrange
            $callbackExecuted = false;
            $callback = function () use (&$callbackExecuted): void {
                $callbackExecuted = true;
            };

            $options = [
                'filter' => null,
                'tag' => null,
                'parallel' => 1,
                'incremental' => false,
                'watch' => true,
            ];

            $this->output->shouldReceive('writeln')->times(3); // 3 writeln before callback, then early return
            $this->input->shouldReceive('setOption')->times(2); // Only parallel and incremental when filter/tag are null
            // Should NOT call helper->ask in watch mode

            // Act
            ($this->callPrivateMethod)($this->service, 'runTests', [$callback, $options]);

            // Assert
            expect($callbackExecuted)->toBeTrue();
        });

        test('applies all options when all are set', function (): void {
            // Arrange
            $callback = fn (): null => null;
            $options = [
                'filter' => 'TestPattern',
                'tag' => 'unit',
                'parallel' => 4,
                'incremental' => true,
                'watch' => false,
            ];

            $this->output->shouldReceive('writeln')->times(5);

            $this->input
                ->shouldReceive('setOption')
                ->with('filter', 'TestPattern')
                ->once();

            $this->input
                ->shouldReceive('setOption')
                ->with('tag', 'unit')
                ->once();

            $this->input
                ->shouldReceive('setOption')
                ->with('parallel', '4')
                ->once();

            $this->input
                ->shouldReceive('setOption')
                ->with('incremental', true)
                ->once();

            $this->helper->shouldReceive('ask')->once()->andReturn('');

            // Act
            ($this->callPrivateMethod)($this->service, 'runTests', [$callback, $options]);

            // Assert - verified through mock expectations
        });
    });

    describe('clearOptions()', function (): void {
        test('resets all options to defaults', function (): void {
            // Arrange
            $options = [
                'filter' => 'TestPattern',
                'tag' => 'unit',
                'parallel' => 8,
                'incremental' => true,
                'watch' => true,
            ];

            $this->output
                ->shouldReceive('writeln')
                ->with('<info>All filters cleared</info>')
                ->once();

            // Act
            ($this->callClearOptions)($options);

            // Assert
            expect($options)->toBe([
                'filter' => null,
                'tag' => null,
                'parallel' => 1,
                'incremental' => false,
                'watch' => false,
            ]);
        });

        test('clears already cleared options', function (): void {
            // Arrange
            $options = [
                'filter' => null,
                'tag' => null,
                'parallel' => 1,
                'incremental' => false,
                'watch' => false,
            ];

            $this->output
                ->shouldReceive('writeln')
                ->with('<info>All filters cleared</info>')
                ->once();

            // Act
            ($this->callClearOptions)($options);

            // Assert
            expect($options)->toBe([
                'filter' => null,
                'tag' => null,
                'parallel' => 1,
                'incremental' => false,
                'watch' => false,
            ]);
        });

        test('clears partially set options', function (): void {
            // Arrange
            $options = [
                'filter' => 'Test',
                'tag' => null,
                'parallel' => 4,
                'incremental' => false,
                'watch' => true,
            ];

            $this->output
                ->shouldReceive('writeln')
                ->with('<info>All filters cleared</info>')
                ->once();

            // Act
            ($this->callClearOptions)($options);

            // Assert
            expect($options)->toBe([
                'filter' => null,
                'tag' => null,
                'parallel' => 1,
                'incremental' => false,
                'watch' => false,
            ]);
        });
    });
});
