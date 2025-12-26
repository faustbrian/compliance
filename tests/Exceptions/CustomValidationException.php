<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Exceptions;

use InvalidArgumentException;

/**
 * Test exception for custom validation scenarios.
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomValidationException extends InvalidArgumentException
{
    public static function occurred(): self
    {
        return new self('Custom validation exception');
    }
}
