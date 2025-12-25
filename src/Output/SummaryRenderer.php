<?php

declare(strict_types=1);

namespace Cline\Compliance\Output;

use Cline\Compliance\ValueObjects\TestSuite;

use function Termwind\render;

final readonly class SummaryRenderer
{
    /**
     * @param  array<int, TestSuite>  $suites
     */
    public function render(array $suites): void
    {
        foreach ($suites as $suite) {
            $this->renderSuite($suite);
        }

        $this->renderSummary($suites);
    }

    private function renderSuite(TestSuite $suite): void
    {
        $passRateColor = match (true) {
            $suite->passRate() === 100.0 => 'text-green',
            $suite->passRate() >= 95.0 => 'text-yellow',
            default => 'text-red',
        };

        // Calculate dots for alignment (Laravel RouteListCommand style)
        $label = $suite->name;
        $stats = sprintf('%d/%d tests', $suite->passedTests(), $suite->totalTests());
        $totalWidth = 60;
        $dotsNeeded = max(1, $totalWidth - mb_strlen($label) - mb_strlen($stats) - 1);
        $dots = str_repeat('.', $dotsNeeded);

        render(sprintf(
            <<<'HTML'
                <div class="mx-2">
                    <span class="text-white font-bold">%s</span> <span class="text-gray">%s</span>
                    <span class="ml-1 text-cyan">%4d</span><span class="text-gray">/</span><span class="text-white">%-4d tests</span>
                    <span class="ml-1 %s font-bold">(%.1f%%)</span>
                </div>
            HTML,
            $label,
            $dots,
            $suite->passedTests(),
            $suite->totalTests(),
            $passRateColor,
            $suite->passRate(),
        ));
    }

    /**
     * @param  array<int, TestSuite>  $suites
     */
    private function renderSummary(array $suites): void
    {
        $totalTests = array_sum(array_map(fn (TestSuite $s) => $s->totalTests(), $suites));
        $totalPassed = array_sum(array_map(fn (TestSuite $s) => $s->passedTests(), $suites));
        $totalFailed = array_sum(array_map(fn (TestSuite $s) => $s->failedTests(), $suites));
        $totalDuration = array_sum(array_map(fn (TestSuite $s) => $s->duration, $suites));

        $allPassed = $totalFailed === 0;

        if ($allPassed) {
            render(<<<'HTML'
                <div class="mx-2 px-2 py-1 bg-green">
                    <span class="font-bold text-white">🎉 ALL TESTS PASSED - 100% COMPLIANCE</span>
                </div>
            HTML);
        } else {
            // Summary with dots alignment
            $summaryItems = [
                ['label' => 'Total', 'value' => number_format($totalTests).' tests', 'color' => 'text-white'],
                ['label' => 'Passed', 'value' => number_format($totalPassed).' tests', 'color' => 'text-green'],
                ['label' => 'Failed', 'value' => number_format($totalFailed).' tests', 'color' => 'text-red'],
                ['label' => 'Duration', 'value' => sprintf('%.2fs', $totalDuration), 'color' => 'text-cyan'],
            ];

            $totalWidth = 60;

            foreach ($summaryItems as $item) {
                $dotsNeeded = $totalWidth - mb_strlen($item['label']) - mb_strlen($item['value']) - 1;
                $dots = ' '.str_repeat('.', max(1, $dotsNeeded));

                render(sprintf(
                    <<<'HTML'
                        <div class="mx-2">
                            <span class="text-gray">%s%s</span>
                            <span class="ml-1 %s">%s</span>
                        </div>
                    HTML,
                    $item['label'],
                    $dots,
                    $item['color'],
                    $item['value'],
                ));
            }
        }
    }
}
