<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * Exception thrown when a compliance configuration file cannot be found.
 */
final class ConfigurationFileNotFoundException extends RuntimeException implements ComplianceException
{
    public static function atPath(string $path): self
    {
        return new self(sprintf('Compliance configuration file not found: %s', $path));
    }
}
