[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

# Prism

Prism is purpose-built for **validation testing**. Instead of writing repetitive PHPUnit tests, you write declarative test cases and Prism executes them with powerful features like parallel execution, snapshot testing, fuzzing, and benchmarking.

## Requirements

> **Requires [PHP 8.5+](https://php.net/releases/)**

## Installation

```bash
composer require cline/prism
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
