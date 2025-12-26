<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Application;

test('application can be instantiated', function (): void {
    $app = new Application();

    expect($app)->toBeInstanceOf(Application::class)
        ->and($app->getName())->toBe('Prism')
        ->and($app->getVersion())->toBeString();
});
