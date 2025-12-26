<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Output;

use function assert;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Renders validator comparison results showing discrepancies between validators.
 *
 * Displays differences in validation outcomes across multiple validator
 * implementations, helping identify inconsistencies and edge cases. Shows
 * which tests produced different results across validators with detailed
 * outcome information and agreement percentages.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ComparisonRenderer
{
    /**
     * Render validator comparison report.
     *
     * Creates a formatted comparison report showing tests where different validators
     * produced conflicting results. Displays validator names, total test counts,
     * and detailed discrepancy information including expected vs actual validation
     * outcomes and pass/fail status for each validator.
     *
     * @param array<string, mixed> $comparisonData Comparison data from ValidatorComparisonService
     *                                             containing validators array, total_tests count,
     *                                             discrepancies_count, and detailed discrepancies
     *                                             array with test outcomes per validator
     *
     * @return string Formatted comparison report with discrepancy details or success message
     */
    public function render(array $comparisonData): string
    {
        if (isset($comparisonData['error'])) {
            $error = $comparisonData['error'];
            assert(is_string($error));

            return sprintf("<fg=red>%s</>\n", $error);
        }

        $output = "\n<fg=cyan;options=bold>Validator Comparison Report</>\n\n";

        $validators = $comparisonData['validators'] ?? [];
        assert(is_array($validators));

        $totalTests = $comparisonData['total_tests'] ?? 0;
        assert(is_int($totalTests));

        $discrepanciesCount = $comparisonData['discrepancies_count'] ?? 0;
        assert(is_int($discrepanciesCount));

        $discrepancies = $comparisonData['discrepancies'] ?? [];
        assert(is_array($discrepancies));

        $output .= sprintf("Validators: %s\n", implode(', ', $validators));
        $output .= sprintf("Total Tests: %d\n", $totalTests);
        $output .= sprintf("Discrepancies: %d\n\n", $discrepanciesCount);

        if ($discrepanciesCount === 0) {
            return $output."<fg=green>✓ All validators produced identical results!</>\n";
        }

        $output .= sprintf("<fg=yellow>Found %d test(s) with differing results:</>\n\n", $discrepanciesCount);

        foreach ($discrepancies as $index => $discrepancy) {
            assert(is_int($index));
            assert(is_array($discrepancy));

            $testId = $discrepancy['test_id'] ?? 'unknown';
            assert(is_string($testId));

            $description = $discrepancy['description'] ?? 'No description';
            assert(is_string($description));

            $outcomes = $discrepancy['outcomes'] ?? [];
            assert(is_array($outcomes));

            $agreement = $discrepancy['agreement'] ?? '0%';
            assert(is_string($agreement));

            $output .= sprintf("<fg=yellow>%d. %s</>\n", $index + 1, $testId);
            $output .= sprintf("   Description: %s\n", $description);
            $output .= sprintf("   Agreement: %s\n", $agreement);
            $output .= "   Results:\n";

            foreach ($outcomes as $validator => $outcome) {
                assert(is_string($validator));
                assert(is_array($outcome));

                $passed = $outcome['passed'] ?? false;
                $actual = $outcome['actual'] ?? false;
                $expected = $outcome['expected'] ?? false;

                $status = $passed ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
                $validStr = $actual ? 'valid' : 'invalid';
                $expectedStr = $expected ? 'valid' : 'invalid';

                $output .= sprintf(
                    "     %s: %s (expected: %s, got: %s)\n",
                    $validator,
                    $status,
                    $expectedStr,
                    $validStr,
                );
            }

            $output .= "\n";
        }

        return $output;
    }
}
