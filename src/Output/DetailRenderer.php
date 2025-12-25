<?php

declare(strict_types=1);

namespace Cline\Compliance\Output;

use Cline\Compliance\ValueObjects\TestResult;
use Cline\Compliance\ValueObjects\TestSuite;

use function Termwind\render;

final readonly class DetailRenderer
{
    public function render(TestSuite $suite): void
    {
        $failures = $suite->failures();

        if (count($failures) === 0) {
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
