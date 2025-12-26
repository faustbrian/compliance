<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support;

use Cline\Prism\Contracts\PrismTestInterface;
use Cline\Prism\Contracts\ValidationResult;

use function json_decode;

/**
 * Test implementation of PrismTestInterface for unit testing.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class TestPrismImplementation implements PrismTestInterface
{
    public function __construct(
        private string $name,
        private string $testDirectory,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getValidatorClass(): string
    {
        return 'TestValidator';
    }

    public function getTestDirectory(): string
    {
        return $this->testDirectory;
    }

    public function validate(mixed $data, mixed $schema): ValidationResult
    {
        return new TestValidationResult();
    }

    public function getTestFilePatterns(): array
    {
        return ['*.json'];
    }

    public function decodeJson(string $json): mixed
    {
        return json_decode($json, true);
    }

    public function shouldIncludeFile(string $filePath): bool
    {
        return true;
    }
}
