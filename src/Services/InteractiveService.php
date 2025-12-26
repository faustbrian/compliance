<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use Cline\Prism\Exceptions\InvalidWorkerCountException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

use function assert;
use function is_bool;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Service for interactive test execution with menu-driven test selection.
 *
 * Provides an interactive interface allowing users to filter tests, configure
 * execution options, and run tests through a conversational menu system.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class InteractiveService
{
    /**
     * Create a new interactive service instance.
     *
     * @param QuestionHelper  $helper Symfony console question helper for prompting user input
     *                                and collecting responses through an interactive interface
     * @param InputInterface  $input  Symfony console input interface for reading command line
     *                                arguments and options provided by the user
     * @param OutputInterface $output Symfony console output interface for writing messages,
     *                                status updates, and formatted information to the terminal
     */
    public function __construct(
        private QuestionHelper $helper,
        private InputInterface $input,
        private OutputInterface $output,
    ) {}

    /**
     * Run interactive mode with menu-driven test execution.
     *
     * Presents a continuous loop displaying current configuration and prompting
     * the user to select actions such as running tests, setting filters, configuring
     * parallel workers, or toggling features. Each selection updates the configuration
     * state and the menu is re-displayed until the user explicitly chooses to exit.
     *
     * @param  callable $callback Callback function to execute tests with current option
     *                            configuration. Receives no arguments and is responsible
     *                            for running the test suite with options set via InputInterface.
     * @return never    Runs indefinitely in a loop until user selects exit action
     */
    public function run(callable $callback): never
    {
        $this->output->writeln('<info>Interactive Mode - Configure and run tests</info>');

        $options = [
            'filter' => null,
            'tag' => null,
            'parallel' => 1,
            'incremental' => false,
            'watch' => false,
        ];

        /** @phpstan-ignore while.alwaysTrue (Intentional infinite loop, exits via user action) */
        while (true) {
            $this->output->writeln('');
            $this->displayCurrentOptions($options);

            $action = $this->promptAction();

            match ($action) {
                'run' => $this->runTests($callback, $options),
                'filter' => $options['filter'] = $this->promptFilter(),
                'tag' => $options['tag'] = $this->promptTag(),
                'parallel' => $options['parallel'] = $this->promptParallel(),
                'incremental' => $options['incremental'] = !$options['incremental'],
                'watch' => $options['watch'] = !$options['watch'],
                'clear' => $this->clearOptions($options),
                'exit' => exit(0),
                default => null,
            };
        }
    }

    /**
     * Display current configuration options to the user.
     *
     * Outputs a formatted list of all configurable options including filter patterns,
     * tag filters, parallel worker count, and feature toggles, showing their current
     * values or indicating when options are not set.
     *
     * @param array<string, mixed> $options Current option values including filter, tag,
     *                                      parallel worker count, incremental mode, and watch mode settings
     */
    private function displayCurrentOptions(array $options): void
    {
        assert(is_string($options['filter']) || $options['filter'] === null);
        assert(is_string($options['tag']) || $options['tag'] === null);
        assert(is_int($options['parallel']));
        assert(is_bool($options['incremental']));
        assert(is_bool($options['watch']));

        $this->output->writeln('<fg=cyan>Current Configuration:</>');
        $this->output->writeln(sprintf('  Filter: %s', $options['filter'] ?? '<none>'));
        $this->output->writeln(sprintf('  Tag: %s', $options['tag'] ?? '<none>'));
        $this->output->writeln(sprintf('  Parallel: %d worker(s)', $options['parallel']));
        $this->output->writeln(sprintf('  Incremental: %s', $options['incremental'] ? 'enabled' : 'disabled'));
        $this->output->writeln(sprintf('  Watch: %s', $options['watch'] ? 'enabled' : 'disabled'));
    }

    /**
     * Prompt user to select an action from the menu.
     *
     * Displays a choice question with all available actions including running tests,
     * configuring filters and options, toggling features, clearing settings, or exiting.
     * Returns the selected action identifier for processing by the main loop.
     *
     * @return string Selected action identifier (run|filter|tag|parallel|incremental|watch|clear|exit)
     */
    private function promptAction(): string
    {
        $question = new ChoiceQuestion(
            'Select action:',
            [
                'run' => 'Run tests with current configuration',
                'filter' => 'Set name filter (regex)',
                'tag' => 'Set tag filter',
                'parallel' => 'Configure parallel workers',
                'incremental' => 'Toggle incremental mode',
                'watch' => 'Toggle watch mode',
                'clear' => 'Clear all filters',
                'exit' => 'Exit',
            ],
            'run',
        );

        $question->setErrorMessage('Invalid selection: %s');

        $answer = $this->helper->ask($this->input, $this->output, $question);

        assert(is_string($answer));

        return $answer;
    }

    /**
     * Prompt user for name filter pattern.
     *
     * Asks the user to enter a regular expression pattern to filter tests by name.
     * An empty input clears any existing filter. The pattern is used to match
     * against test descriptions during test execution.
     *
     * @return null|string Filter pattern as regex string, or null if cleared
     */
    private function promptFilter(): ?string
    {
        $question = new Question('Enter name filter pattern (regex, empty to clear): ');

        $answer = $this->helper->ask($this->input, $this->output, $question);

        assert(is_string($answer) || $answer === null);

        return $answer === '' || $answer === null ? null : $answer;
    }

    /**
     * Prompt user for tag filter.
     *
     * Asks the user to enter a tag name to filter tests by their assigned tags.
     * An empty input clears any existing tag filter. Only tests with the matching
     * tag will be executed when a filter is active.
     *
     * @return null|string Tag name to filter by, or null if cleared
     */
    private function promptTag(): ?string
    {
        $question = new Question('Enter tag to filter by (empty to clear): ');

        $answer = $this->helper->ask($this->input, $this->output, $question);

        assert(is_string($answer) || $answer === null);

        return $answer === '' || $answer === null ? null : $answer;
    }

    /**
     * Prompt user for number of parallel workers.
     *
     * Asks the user to specify how many parallel processes to use for test execution.
     * Validates that the value is at least 1. A value of 1 means sequential execution,
     * while higher values enable parallel processing across multiple CPU cores.
     *
     * @throws InvalidWorkerCountException If user enters a value less than 1
     * @return int                         Number of parallel workers (minimum 1)
     */
    private function promptParallel(): int
    {
        $question = new Question('Enter number of parallel workers (1 for sequential): ', '1');
        $question->setValidator(function (mixed $answer): int {
            assert(is_string($answer) || is_int($answer));
            $value = (int) $answer;

            if ($value < 1) {
                throw InvalidWorkerCountException::mustBeAtLeastOne();
            }

            return $value;
        });

        $answer = $this->helper->ask($this->input, $this->output, $question);

        assert(is_int($answer));

        return $answer;
    }

    /**
     * Execute tests using current configuration options.
     *
     * Applies all configured options to the input interface, executes the test
     * callback, and provides user feedback. In non-watch mode, waits for user
     * acknowledgment before returning to the menu.
     *
     * @param callable             $callback Callback function to execute test suite
     * @param array<string, mixed> $options  Current configuration including filter,
     *                                       tag, parallel workers, incremental mode, and watch mode
     */
    private function runTests(callable $callback, array $options): void
    {
        $this->output->writeln('');
        $this->output->writeln('<fg=green>Running tests...</>');
        $this->output->writeln('');

        // Apply options to input
        if ($options['filter'] !== null) {
            $this->input->setOption('filter', $options['filter']);
        }

        if ($options['tag'] !== null) {
            $this->input->setOption('tag', $options['tag']);
        }

        assert(is_int($options['parallel']));
        $this->input->setOption('parallel', (string) $options['parallel']);
        $this->input->setOption('incremental', $options['incremental']);

        // Execute tests
        $callback();

        if ($options['watch']) {
            return;
        }

        $this->output->writeln('');
        $this->output->writeln('<fg=green>Tests completed. Press Enter to continue...</>');

        $this->helper->ask($this->input, $this->output, new Question(''));
    }

    /**
     * Reset all configuration options to their defaults.
     *
     * Clears all filters and resets parallel workers to 1, incremental mode to disabled,
     * and watch mode to disabled. The options array is modified by reference.
     *
     * @param array<string, mixed> $options Options array to reset, passed by reference
     *                                      to allow modification of the original array
     */
    private function clearOptions(array &$options): void
    {
        $options['filter'] = null;
        $options['tag'] = null;
        $options['parallel'] = 1;
        $options['incremental'] = false;
        $options['watch'] = false;

        $this->output->writeln('<info>All filters cleared</info>');
    }
}
