<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\Contracts;

interface ValidationResult
{
    /**
     * Check if validation passed.
     */
    public function isValid(): bool;

    /**
     * Get validation errors.
     *
     * @return array<int, string>
     */
    public function getErrors(): array;
}
