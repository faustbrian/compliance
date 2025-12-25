<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\ValueObjects;

use function array_filter;
use function array_values;
use function count;

/**
 * @psalm-immutable
 */
final readonly class TestSuite
{
    /**
     * @param array<int, TestResult> $results
     */
    public function __construct(
        public string $name,
        public array $results,
        public float $duration,
    ) {}

    public function totalTests(): int
    {
        return count($this->results);
    }

    public function passedTests(): int
    {
        return count(array_filter($this->results, fn (TestResult $r) => $r->passed));
    }

    public function failedTests(): int
    {
        return count(array_filter($this->results, fn (TestResult $r) => !$r->passed));
    }

    public function passRate(): float
    {
        if ($this->totalTests() === 0) {
            return 0.0;
        }

        return ($this->passedTests() / $this->totalTests()) * 100;
    }

    /**
     * @return array<int, TestResult>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->results, fn (TestResult $r) => !$r->passed));
    }
}
