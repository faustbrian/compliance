<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Output;

use Cline\Prism\Services\CustomAssertionService;

use function count;
use function sprintf;

/**
 * Renders available custom assertions for user reference.
 *
 * Displays registered custom assertion names to help users understand
 * which assertions are available for use in their test files. Outputs
 * a formatted list with count and usage instructions.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class AssertionRenderer
{
    /**
     * Render list of available custom assertions.
     *
     * Creates a formatted string showing all registered custom assertion names
     * with color-coded output suitable for terminal display. Returns a warning
     * message if no assertions are registered.
     *
     * @param CustomAssertionService $service Assertion service containing all registered
     *                                        custom assertions to be displayed in the list
     *
     * @return string Formatted output string with assertion list or empty state message
     */
    public function render(CustomAssertionService $service): string
    {
        $names = $service->getAssertionNames();
        $count = count($names);

        if ($count === 0) {
            return "\n<fg=yellow>No custom assertions registered.</>\n\n";
        }

        $output = "\n<fg=cyan;options=bold>Available Custom Assertions</>\n\n";
        $output .= sprintf("Total: %d\n\n", $count);

        foreach ($names as $name) {
            $output .= sprintf("  • %s\n", $name);
        }

        return $output."\n<fg=gray>Use these assertions in your test files with the 'assertion' field.</>\n";
    }
}
