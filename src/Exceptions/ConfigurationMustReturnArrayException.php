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
 *
 * Raised when the compliance.php configuration file exists but returns an
 * invalid value type. The configuration file must return an array of
 * ComplianceTestInterface implementations. This error typically indicates
 * a syntax error or incorrect return statement in the configuration file.
 */
final class ConfigurationMustReturnArrayException extends RuntimeException implements ComplianceException
{
    /**
     * Create a new exception for invalid configuration file return type.
     *
     * Generates an exception indicating the configuration file returned
     * a value that is not an array when an array of test configurations
     * was expected.
     *
     * @return self The configured exception instance
     */
    public static function fromFile(): self
    {
        return new self('Compliance configuration must return an array');
    }
}
