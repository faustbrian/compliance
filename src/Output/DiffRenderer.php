<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Output;

use Cline\Prism\ValueObjects\TestResult;
use Symfony\Component\Console\Output\OutputInterface;

use const JSON_PRETTY_PRINT;

use function array_keys;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_encode;
use function preg_replace;
use function range;
use function sprintf;
use function str_repeat;

/**
 * Enhanced renderer for displaying test failures with formatted JSON diff.
 *
 * Provides syntax-highlighted JSON output with color-coded values to make
 * debugging easier. Highlights strings, numbers, booleans, and nulls with
 * distinct colors for quick visual scanning of test data.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class DiffRenderer
{
    public function __construct(
        private OutputInterface $output,
    ) {}

    /**
     * Render detailed failure information with enhanced JSON formatting.
     *
     * Displays test metadata (ID, file, group, validation status) followed by
     * syntax-highlighted JSON representation of the test data. Error messages
     * are shown in red, and the JSON data is color-coded by value type for
     * easier visual debugging.
     *
     * @param int        $number  Sequential failure number for display ordering
     * @param TestResult $failure The failed test result containing all failure
     *                            details, error messages, and test data
     */
    public function renderFailure(int $number, TestResult $failure): void
    {
        $this->output->writeln('');
        $this->output->writeln(sprintf('<fg=red;options=bold>%d. %s</>', $number, $failure->description));
        $this->output->writeln(sprintf('   <fg=gray>Test ID:</> %s', $failure->id));
        $this->output->writeln(sprintf('   <fg=gray>File:</> %s', $failure->file));
        $this->output->writeln(sprintf('   <fg=gray>Group:</> %s', $failure->group));
        $this->output->writeln(sprintf(
            '   <fg=gray>Expected Validation:</> <fg=%s>%s</>',
            $failure->expected ? 'green' : 'yellow',
            $failure->expected ? 'VALID' : 'INVALID',
        ));
        $this->output->writeln(sprintf(
            '   <fg=gray>Actual Validation:</> <fg=%s>%s</>',
            $failure->actual ? 'green' : 'red',
            $failure->actual ? 'VALID' : 'INVALID',
        ));

        if ($failure->error !== null) {
            $this->output->writeln(sprintf('   <fg=red;options=bold>Error:</> <fg=red>%s</>', $failure->error));
        }

        if ($failure->duration > 0) {
            $this->output->writeln(sprintf('   <fg=gray>Duration:</> %.2fms', $failure->duration * 1_000));
        }

        $this->output->writeln('');
        $this->output->writeln('   <fg=gray>Test Data:</>');
        $this->renderJsonData($failure->data, 3);
        $this->output->writeln('');
        $this->output->writeln(str_repeat('─', 80));
    }

    /**
     * Render JSON data with syntax highlighting.
     *
     * Recursively formats and color-codes JSON data structures with proper
     * indentation. Different value types receive distinct colors: strings in
     * yellow, numbers in cyan, booleans/nulls in magenta. This makes complex
     * nested structures easier to read and debug.
     *
     * @param mixed $data   The data to render (string, number, array, object, etc.)
     * @param int   $indent Current indentation level in spaces for nested structures
     */
    private function renderJsonData(mixed $data, int $indent = 0): void
    {
        $prefix = str_repeat(' ', $indent);

        if (null === $data) {
            $this->output->writeln(sprintf('%s<fg=magenta>null</>', $prefix));
        } elseif (is_bool($data)) {
            $this->output->writeln(sprintf('%s<fg=magenta>%s</>', $prefix, $data ? 'true' : 'false'));
        } elseif (is_int($data) || is_float($data)) {
            $this->output->writeln(sprintf('%s<fg=cyan>%s</>', $prefix, (string) $data));
        } elseif (is_string($data)) {
            $this->output->writeln(sprintf('%s<fg=yellow>"%s"</>', $prefix, $data));
        } elseif (is_array($data)) {
            if ($data === []) {
                $this->output->writeln(sprintf('%s<fg=gray>[]</>', $prefix));

                return;
            }

            $isAssoc = array_keys($data) !== range(0, count($data) - 1);

            if ($isAssoc) {
                $this->output->writeln(sprintf('%s<fg=gray>{</>', $prefix));

                foreach ($data as $key => $value) {
                    $this->output->write(sprintf('%s  <fg=blue>"%s"</>: ', $prefix, $key));
                    $this->renderInlineValue($value, $indent + 2);
                }

                $this->output->writeln(sprintf('%s<fg=gray>}</>', $prefix));
            } else {
                $this->output->writeln(sprintf('%s<fg=gray>[</>', $prefix));

                foreach ($data as $value) {
                    $this->output->write(sprintf('%s  ', $prefix));
                    $this->renderInlineValue($value, $indent + 2);
                }

                $this->output->writeln(sprintf('%s<fg=gray>]</>', $prefix));
            }
        } else {
            // Fallback to JSON encoding for complex types
            $json = json_encode($data, JSON_PRETTY_PRINT);

            if ($json === false) {
                $this->output->writeln(sprintf('%s<fg=red>(unserializable)</>', $prefix));

                return;
            }

            $this->output->writeln($this->highlightJson($json, $indent));
        }
    }

    /**
     * Render a value inline (on same line as key).
     *
     * For simple values, displays them inline. For complex structures like
     * nested arrays or objects, delegates to the full recursive rendering
     * logic with proper indentation.
     *
     * @param mixed $value  The value to render inline
     * @param int   $indent Current indentation level for nested structures
     */
    private function renderInlineValue(mixed $value, int $indent): void
    {
        if (null === $value) {
            $this->output->writeln('<fg=magenta>null</>');
        } elseif (is_bool($value)) {
            $this->output->writeln(sprintf('<fg=magenta>%s</>', $value ? 'true' : 'false'));
        } elseif (is_int($value) || is_float($value)) {
            $this->output->writeln(sprintf('<fg=cyan>%s</>', (string) $value));
        } elseif (is_string($value)) {
            $this->output->writeln(sprintf('<fg=yellow>"%s"</>', $value));
        } else {
            // For arrays/objects, render on next line with proper indentation
            $this->output->writeln('');
            $this->renderJsonData($value, $indent);
        }
    }

    /**
     * Apply syntax highlighting to JSON string.
     *
     * Uses regex replacements to colorize JSON syntax elements: property names
     * in blue, string values in yellow, numbers in cyan, and keywords (true,
     * false, null) in magenta. Preserves indentation while adding color tags.
     *
     * @param  string $json   The JSON string to highlight with ANSI color codes
     * @param  int    $indent Base indentation level to add to all lines
     * @return string Colorized JSON string with Symfony Console formatting tags
     */
    private function highlightJson(string $json, int $indent = 0): string
    {
        $prefix = str_repeat(' ', $indent);

        // Add color tags to JSON syntax
        $highlighted = preg_replace(
            [
                '/"([^"]+)"\s*:/',                    // Keys
                '/"([^"]+)"/',                        // String values
                '/\b(\d+\.?\d*)\b/',                  // Numbers
                '/\b(true|false|null)\b/',            // Keywords
            ],
            [
                '<fg=blue>"$1"</>:',
                '<fg=yellow>"$1"</>',
                '<fg=cyan>$1</>',
                '<fg=magenta>$1</>',
            ],
            $json,
        );

        // Add prefix to each line
        $lines = explode("\n", $highlighted ?? $json);
        $result = [];

        foreach ($lines as $line) {
            $result[] = $prefix.$line;
        }

        return implode("\n", $result);
    }
}
