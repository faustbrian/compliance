<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Exceptions;

use Throwable;

/**
 * Marker interface for all Prism package exceptions.
 *
 * Provides a common type for all exceptions thrown by the Prism package,
 * enabling consumers to catch any Prism-specific exception with a single
 * catch block. All custom exceptions in the Prism package implement this interface.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface PrismException extends Throwable
{
    // Marker interface - no methods required
}
