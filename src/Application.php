<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism;

use Cline\Prism\Commands\TestCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * Main prism testing CLI application.
 *
 * Provides a command-line interface for running prism validation tests
 * against JSON Schema specifications or similar standards. The application
 * automatically configures the test command as the default entry point for
 * immediate execution without explicit command specification.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Application extends SymfonyApplication
{
    /**
     * Application semantic version number.
     */
    private const string VERSION = '1.0.0';

    /**
     * Create a new prism application instance.
     *
     * Initializes the Symfony Console application with the Prism branding
     * and version, registers the test command, and configures it as the default
     * command to streamline CLI usage.
     */
    public function __construct()
    {
        parent::__construct('Prism', self::VERSION);

        $this->addCommand(
            new TestCommand(),
        );
        $this->setDefaultCommand('test', true);
    }
}
