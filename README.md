[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

# Compliance

Beautiful compliance testing CLI for PHP projects with Termwind-powered output.

## Requirements

> **Requires [PHP 8.5+](https://php.net/releases/)**

## Installation

```bash
composer require cline/compliance
```

## Quick Start

Create a `compliance.php` configuration file:

```php
<?php

use Cline\Compliance\Contracts\ComplianceTestInterface;
use Cline\Compliance\Contracts\ValidationResult;

return [
    new class implements ComplianceTestInterface {
        public function getName(): string
        {
            return 'My Compliance Test';
        }

        public function getValidatorClass(): string
        {
            return MyValidator::class;
        }

        public function getTestDirectory(): string
        {
            return __DIR__.'/tests/compliance';
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

Run your compliance tests:

```bash
vendor/bin/compliance test
```

## Documentation

- **[Getting Started](https://docs.cline.sh/compliance/getting-started/)** - Installation and basic usage
- **[Configuration](https://docs.cline.sh/compliance/configuration/)** - Configure compliance test suites
- **[Writing Tests](https://docs.cline.sh/compliance/writing-tests/)** - Create comprehensive test files
- **[API Reference](https://docs.cline.sh/compliance/api-reference/)** - Complete API documentation

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

[ico-tests]: https://github.com/faustbrian/compliance/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/compliance.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/compliance.svg

[link-tests]: https://github.com/faustbrian/compliance/actions
[link-packagist]: https://packagist.org/packages/cline/compliance
[link-downloads]: https://packagist.org/packages/cline/compliance
[link-security]: https://github.com/faustbrian/compliance/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
