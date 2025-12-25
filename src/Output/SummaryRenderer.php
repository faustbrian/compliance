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
        render(<<<'HTML'
            <div class="my-1">
                <div class="px-2 py-1 bg-blue">
                    <span class="font-bold text-white">Compliance Test Suite</span>
                </div>
            </div>
        HTML);

        foreach ($suites as $suite) {
            $this->renderSuite($suite);
        }

        render('<div class="my-1"></div>');

        $this->renderSummary($suites);
    }

    private function renderSuite(TestSuite $suite): void
    {
        $status = $suite->failedTests() === 0 ? '✓' : '✗';
        $statusColor = $suite->failedTests() === 0 ? 'text-green' : 'text-red';
        $passRateColor = match (true) {
            $suite->passRate() === 100.0 => 'text-green',
            $suite->passRate() >= 95.0 => 'text-yellow',
            default => 'text-red',
        };

        render(sprintf(
            <<<'HTML'
                <div class="mx-2">
                    <span class="%s font-bold">%s</span>
                    <span class="ml-1 text-white font-bold">%-20s</span>
                    <span class="ml-2 text-cyan">%4d</span><span class="text-gray">/</span><span class="text-white">%4d tests</span>
                    <span class="ml-2 %s font-bold">(%.1f%%)</span>
                </div>
            HTML,
            $statusColor,
            $status,
            $suite->name.':',
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
            render(sprintf(
                <<<'HTML'
                    <div class="mx-2">
                        <div class="text-yellow font-bold">Some tests failed.</div>
                        <div class="mt-1">
                            <div><span class="text-gray">Total:   </span> <span class="text-white">%d tests</span></div>
                            <div><span class="text-gray">Passed:  </span> <span class="text-green">%d tests</span></div>
                            <div><span class="text-gray">Failed:  </span> <span class="text-red">%d tests</span></div>
                            <div><span class="text-gray">Duration:</span> <span class="text-cyan">%.2fs</span></div>
                        </div>
                    </div>
                HTML,
                $totalTests,
                $totalPassed,
                $totalFailed,
                $totalDuration,
            ));
        }
    }
}
