<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a compliance configuration file does not return an array.
 */
final class ConfigurationMustReturnArrayException extends RuntimeException implements ComplianceException
{
    public static function fromFile(): self
    {
        return new self('Compliance configuration must return an array');
    }
}
