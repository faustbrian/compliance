<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\Output;

use Cline\Compliance\ValueObjects\TestResult;
use Cline\Compliance\ValueObjects\TestSuite;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

use function count;
use function json_encode;
use function sprintf;
use function Termwind\render;

/**
 * Renders detailed failure information using Termwind terminal formatting.
 *
 * Provides rich, colorized output for test failures in interactive terminal
 * environments. Uses Termwind HTML-like syntax for styling, colors, and layout,
 * creating visually distinct failure reports with color-coded status indicators,
 * formatted JSON data, and structured error messages.
 *
 * @psalm-immutable
 */
final readonly class DetailRenderer
{
    /**
     * Render detailed failure information for a test suite.
     *
     * Displays all failed tests from the suite with rich terminal formatting
     * including a red header banner showing the suite name and failure count,
     * followed by detailed information for each failure. Skips rendering if
     * the suite has no failures.
     *
     * @param TestSuite $suite The test suite containing failed tests to display
     */
    public function render(TestSuite $suite): void
    {
        $failures = $suite->failures();

        if ($failures === []) {
            return;
        }

        render(sprintf(
            <<<'HTML'
                <div class="my-1">
                    <div class="px-2 py-1 bg-red">
                        <span class="font-bold text-white">%s - Failures (%d)</span>
                    </div>
                </div>
            HTML,
            $suite->name,
            count($failures),
        ));

        foreach ($failures as $index => $failure) {
            $this->renderFailure($index + 1, $failure);
        }
    }

    /**
     * Render detailed information for a single failed test.
     *
     * Outputs a formatted block with test number and description, file path,
     * test group, expected vs actual validation results, optional error message,
     * and pretty-printed JSON test data. Uses color coding and indentation for
     * visual hierarchy.
     *
     * @param int        $number  The sequential failure number for display
     * @param TestResult $failure The failed test result to render
     */
    private function renderFailure(int $number, TestResult $failure): void
    {
        $dataJson = json_encode($failure->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $expectedLabel = $failure->expectedValid ? 'VALID' : 'INVALID';
        $actualLabel = $failure->actualValid ? 'VALID' : 'INVALID';

        $output = sprintf(
            <<<'HTML'
                <div class="mx-2 my-1">
                    <div class="text-red font-bold">✗ %d. %s</div>
                    <div class="ml-2 text-gray">File: %s</div>
                    <div class="ml-2 text-gray">Group: %s</div>
                    <div class="ml-2 mt-1">
                        <span class="text-gray">Expected: </span>
                        <span class="text-yellow">%s</span>
                        <span class="text-gray ml-2">Actual: </span>
                        <span class="text-yellow">%s</span>
                    </div>
            HTML,
            $number,
            $failure->description,
            $failure->file,
            $failure->group,
            $expectedLabel,
            $actualLabel,
        );

        if ($failure->error !== null) {
            $output .= sprintf(
                <<<'HTML'
                    <div class="ml-2 mt-1 text-red">Error: %s</div>
                HTML,
                $failure->error,
            );
        }

        $output .= sprintf(
            <<<'HTML'
                    <div class="ml-2 mt-1 text-gray">Data: %s</div>
                </div>
            HTML,
            $dataJson,
        );

        render($output);
    }
}
