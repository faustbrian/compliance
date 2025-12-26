<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Services;

use Cline\Prism\Contracts\PrismTestInterface;
use Cline\Prism\Exceptions\ConfigurationFileNotFoundException;
use Cline\Prism\Exceptions\ConfigurationMustReturnArrayException;

use function array_filter;
use function array_values;
use function file_exists;
use function getcwd;
use function is_array;

/**
 * Loads and validates prism test configurations from PHP files.
 *
 * This service discovers prism configuration files either from an explicitly
 * provided path or from conventional locations (project root or config directory).
 * Validates that configuration files return arrays of PrismTestInterface instances
 * and filters out any invalid entries.
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ConfigLoader
{
    /**
     * Load prism test configurations from a file.
     *
     * When a path is provided, loads and validates that specific configuration file.
     * When no path is provided, searches conventional locations (prism.php in
     * project root, then config/prism.php) and loads the first file found.
     * Filters the returned configuration to ensure only valid PrismTestInterface
     * instances are included.
     *
     * @param  null|string                           $path Optional explicit path to configuration file. When null,
     *                                                     searches conventional locations in the current working directory
     * @throws ConfigurationFileNotFoundException    When explicit path is provided but file does not exist
     * @throws ConfigurationMustReturnArrayException When configuration file does not return an array
     * @return array<int, PrismTestInterface>        Array of valid prism test instances,
     *                                               or empty array if no configuration found
     */
    public function load(?string $path = null): array
    {
        if ($path !== null) {
            if (!file_exists($path)) {
                throw ConfigurationFileNotFoundException::atPath($path);
            }

            $config = require $path;

            if (!is_array($config)) {
                throw ConfigurationMustReturnArrayException::fromFile();
            }

            return array_values(array_filter($config, fn ($item): bool => $item instanceof PrismTestInterface));
        }

        $configPaths = [
            getcwd().'/prism.php',
            getcwd().'/config/prism.php',
        ];

        foreach ($configPaths as $path) {
            if (file_exists($path)) {
                $config = require $path;

                if (is_array($config)) {
                    return array_values(array_filter($config, fn ($item): bool => $item instanceof PrismTestInterface));
                }

                return [];
            }
        }

        return [];
    }
}
