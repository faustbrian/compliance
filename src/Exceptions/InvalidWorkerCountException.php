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
 * Exception thrown when worker count is invalid.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidWorkerCountException extends RuntimeException implements PrismException
{
    public static function mustBeAtLeastOne(): self
    {
        return new self('Number of workers must be at least 1');
    }
}
