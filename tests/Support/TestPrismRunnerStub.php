<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support;

use Cline\Prism\Contracts\PrismTestInterface;
use Cline\Prism\ValueObjects\TestSuite;

/**
 * Test stub for PrismRunner that returns predefined TestSuites.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TestPrismRunnerStub
{
    private array $suites = [];

    public function setSuite(string $validatorName, TestSuite $suite): void
    {
        $this->suites[$validatorName] = $suite;
    }

    public function run(PrismTestInterface $prism): TestSuite
    {
        return $this->suites[$prism->getName()] ?? new TestSuite(
            name: $prism->getName(),
            results: [],
            duration: 0.0,
        );
    }
}
