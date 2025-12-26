<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Exceptions;

use RuntimeException;

/**
 * Test exception for simulating validation errors.
 * @author Brian Faust <brian@cline.sh>
 */
final class ValidationErrorException extends RuntimeException
{
    public static function occurred(): self
    {
        return new self('Validation error occurred');
    }
}
