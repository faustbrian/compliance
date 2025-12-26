[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

# Prism

Beautiful prism testing CLI for PHP projects with Termwind-powered output.

## Requirements

> **Requires [PHP 8.5+](https://php.net/releases/)**

## Installation

```bash
composer require cline/prism
```

## Quick Start

Create a `prism.php` configuration file:

```php
<?php

use Cline\Prism\Contracts\PrismTestInterface;
use Cline\Prism\Contracts\ValidationResult;

return [
    new class implements PrismTestInterface {
        public function getName(): string
        {
            return 'My Prism Test';
        }

        public function getValidatorClass(): string
        {
            return MyValidator::class;
        }

        public function getTestDirectory(): string
        {
            return __DIR__.'/tests/prism';
        }

        public function validate(mixed $data, mixed $schema): ValidationResult
        {
            $validator = new MyValidator();
            return $validator->validate($data, $schema);
        }

        public function getTestFilePatterns(): array
        {
            return ['*.json'];
        }

        public function decodeJson(string $json): mixed
        {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }
    },
];
```

Run your prism tests:

```bash
vendor/bin/prism test
```

## Documentation

- **[Getting Started](https://docs.cline.sh/prism/getting-started/)** - Installation and basic usage
- **[Configuration](https://docs.cline.sh/prism/configuration/)** - Configure test suites
- **[Filtering](https://docs.cline.sh/prism/filtering/)** - Filter tests by name, path, tags
- **[Performance](https://docs.cline.sh/prism/performance/)** - Parallel execution and profiling
- **[Advanced Features](https://docs.cline.sh/prism/advanced-features/)** - Snapshots, fuzzing, validator comparison
- **[Output Formats](https://docs.cline.sh/prism/output-formats/)** - JSON and JUnit XML output
- **[Custom Assertions](https://docs.cline.sh/prism/custom-assertions/)** - Pluggable assertion logic

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/prism/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/prism.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/prism.svg

[link-tests]: https://github.com/faustbrian/prism/actions
[link-packagist]: https://packagist.org/packages/cline/prism
[link-downloads]: https://packagist.org/packages/cline/prism
[link-security]: https://github.com/faustbrian/prism/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
