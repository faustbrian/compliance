<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a prism configuration file does not return an array.
 *
 * Raised when the prism.php configuration file exists but returns an
 * invalid value type. The configuration file must return an array of
 * PrismTestInterface implementations. This error typically indicates
 * a syntax error or incorrect return statement in the configuration file.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigurationMustReturnArrayException extends RuntimeException implements PrismException
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
        return new self('Prism configuration must return an array');
    }
}
