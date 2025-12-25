<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Compliance\Services;

use Cline\Compliance\Contracts\ComplianceTestInterface;
use Cline\Compliance\Exceptions\ConfigurationFileNotFoundException;
use Cline\Compliance\Exceptions\ConfigurationMustReturnArrayException;

use function array_filter;
use function file_exists;
use function getcwd;
use function is_array;

/**
 * @psalm-immutable
 */
final readonly class ConfigLoader
{
    /**
     * @return array<int, ComplianceTestInterface>
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

            return array_filter($config, fn ($item) => $item instanceof ComplianceTestInterface);
        }

        $configPaths = [
            getcwd().'/compliance.php',
            getcwd().'/config/compliance.php',
        ];

        foreach ($configPaths as $path) {
            if (file_exists($path)) {
                $config = require $path;

                if (is_array($config)) {
                    return array_filter($config, fn ($item) => $item instanceof ComplianceTestInterface);
                }

                return [];
            }
        }

        return [];
    }
}
