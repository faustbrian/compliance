<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * Exception thrown when a prism configuration file cannot be found.
 *
 * Raised during configuration loading when the expected prism.php
 * configuration file does not exist at the searched paths. This typically
 * indicates the user needs to create a configuration file or is running
 * the command from the wrong directory.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigurationFileNotFoundException extends RuntimeException implements PrismException
{
    /**
     * Create a new exception for a missing configuration file.
     *
     * Generates an exception with a message indicating the specific path
     * where the configuration file was expected but not found.
     *
     * @param  string $path The file path where the configuration file was expected
     * @return self   The configured exception instance
     */
    public static function atPath(string $path): self
    {
        return new self(sprintf('Prism configuration file not found: %s', $path));
    }
}
