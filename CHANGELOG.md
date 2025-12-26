# Changelog

All notable changes to Prism will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.0](https://github.com/faustbrian/prism/releases/tag/1.6.0) - 2025-12-26

### Added
- Comprehensive test coverage for all services
- Test support classes: TestPrismImplementation, TestPrismRunnerStub, TestValidationResult
- Unit tests for assertions (AnyOfAssertion, StrictEqualityAssertion)
- Unit tests for all output renderers (Assertion, Benchmark, Comparison, Coverage, Diff, Fuzzing, JunitXml, Profile, Snapshot)
- Unit tests for all services (Coverage, CustomAssertion, Filter, Fuzzing, Incremental, Interactive, JsonDiff, ParallelRunner, Progress, ValidatorComparison, Watch)
- ApplicationTest for core application testing

### Changed
- Updated multiple service implementations for better testability
- Refined output renderers based on test feedback
- Improved code quality and coverage metrics

## [1.5.0](https://github.com/faustbrian/prism/releases/tag/1.5.0) - 2025-12-25

### Added
- **Major Feature Release**: Advanced testing capabilities
- Custom assertions framework (AnyOfAssertion, StrictEqualityAssertion)
- AssertionInterface for extensible assertion system
- Multiple new services:
  - BenchmarkService for performance testing
  - CoverageService for code coverage tracking
  - CustomAssertionService for custom validation logic
  - FilterService for test filtering
  - FuzzingService for fuzzing tests
  - IncrementalService for incremental testing
  - InteractiveService for interactive mode
  - JsonDiffService for JSON comparison
  - ParallelRunner for parallel test execution
  - ProgressService for progress tracking
  - SnapshotService for snapshot testing
  - ValidatorComparisonService for comparing validators
  - WatchService for file watching
- New output renderers:
  - AssertionRenderer
  - BenchmarkRenderer
  - ComparisonRenderer
  - CoverageRenderer
  - DiffRenderer
  - FuzzingRenderer
  - JunitXmlRenderer
  - ProfileRenderer
  - SnapshotRenderer
- FEATURES.md documentation
- Example projects with comprehensive documentation
- Custom exceptions: CustomValidationException, ValidationErrorException

### Changed
- Enhanced TestCommand with extensive new options
- Updated application architecture to support new services
- Improved error handling and exceptions
- Moved GitHub workflows directory

## [1.4.0](https://github.com/faustbrian/prism/releases/tag/1.4.0) - 2025-12-25

### Changed
- Minor refinements to core services
- Code quality improvements

## [1.3.0](https://github.com/faustbrian/prism/releases/tag/1.3.0) - 2025-12-25

### Changed
- **Breaking**: Renamed project from "Compliance" to "Prism"
- Renamed binary: `bin/compliance` → `bin/prism`
- Renamed interfaces: `ComplianceTestInterface` → `PrismTestInterface`
- Renamed exceptions: `ComplianceException` → `PrismException`
- Renamed services: `ComplianceRunner` → `PrismRunner`
- Updated all tests and documentation to reflect new branding

## [1.2.18](https://github.com/faustbrian/prism/releases/tag/1.2.18) - 2025-12-25

### Changed
- Minor SummaryRenderer improvements

## [1.2.17](https://github.com/faustbrian/prism/releases/tag/1.2.17) - 2025-12-25

### Changed
- Refactored multiple test files
- Updated rector configuration
- Code style improvements

## [1.2.16](https://github.com/faustbrian/prism/releases/tag/1.2.16) - 2025-12-25

### Added
- Comprehensive test suite for output renderers
- Unit tests for JsonRenderer, XmlRenderer, YamlRenderer
- Enhanced TestCommandTest with 372 new tests
- Comprehensive ComplianceRunnerTest with 1756 tests
- ConfigLoaderTest with 435 tests

### Changed
- Improved output format implementations
- Enhanced test coverage significantly

## [1.2.15](https://github.com/faustbrian/prism/releases/tag/1.2.15) - 2025-12-25

### Changed
- SummaryRenderer refinements

## [1.2.14](https://github.com/faustbrian/prism/releases/tag/1.2.14) - 2025-12-25

### Changed
- SummaryRenderer optimizations

## [1.2.13](https://github.com/faustbrian/prism/releases/tag/1.2.13) - 2025-12-25

### Changed
- SummaryRenderer enhancements

## [1.2.12](https://github.com/faustbrian/prism/releases/tag/1.2.12) - 2025-12-25

### Changed
- CiRenderer and SummaryRenderer improvements

## [1.2.11](https://github.com/faustbrian/prism/releases/tag/1.2.11) - 2025-12-25

### Added
- JsonRenderer for JSON output format (106 lines)
- XmlRenderer for XML output format (189 lines)
- YamlRenderer for YAML output format (102 lines)

### Changed
- Updated composer.json dependencies
- Enhanced TestCommand with output format options
- Updated CiRenderer and SummaryRenderer

## [1.2.10](https://github.com/faustbrian/prism/releases/tag/1.2.10) - 2025-12-25

### Changed
- SummaryRenderer adjustments

## [1.2.9](https://github.com/faustbrian/prism/releases/tag/1.2.9) - 2025-12-25

### Changed
- SummaryRenderer tweaks

## [1.2.8](https://github.com/faustbrian/prism/releases/tag/1.2.8) - 2025-12-25

### Changed
- Significant SummaryRenderer enhancements (68 insertions, 28 deletions)

## [1.2.7](https://github.com/faustbrian/prism/releases/tag/1.2.7) - 2025-12-25

### Changed
- SummaryRenderer improvements

## [1.2.6](https://github.com/faustbrian/prism/releases/tag/1.2.6) - 2025-12-25

### Changed
- Minor SummaryRenderer fix

## [1.2.5](https://github.com/faustbrian/prism/releases/tag/1.2.5) - 2025-12-25

### Changed
- No code changes (tag only)

## [1.2.4](https://github.com/faustbrian/prism/releases/tag/1.2.4) - 2025-12-25

### Changed
- SummaryRenderer updates

## [1.2.3](https://github.com/faustbrian/prism/releases/tag/1.2.3) - 2025-12-25

### Changed
- SummaryRenderer enhancements

## [1.2.2](https://github.com/faustbrian/prism/releases/tag/1.2.2) - 2025-12-25

### Changed
- Test file improvements across integration and unit tests

## [1.2.1](https://github.com/faustbrian/prism/releases/tag/1.2.1) - 2025-12-25

### Changed
- Core service refinements
- Test updates for ConfigLoader and ComplianceRunner

## [1.2.0](https://github.com/faustbrian/prism/releases/tag/1.2.0) - 2025-12-25

### Changed
- Enhanced validation and contract interfaces
- Improved exception handling
- Updated output renderers
- Service layer improvements

## [1.1.0](https://github.com/faustbrian/prism/releases/tag/1.1.0) - 2025-12-25

### Added
- Exception classes:
  - ComplianceException
  - ConfigurationFileNotFoundException
  - ConfigurationMustReturnArrayException
- Integration test: ComplianceFlowTest
- Unit tests for:
  - TestCommand
  - Output renderers (Ci, Detail, Summary)
  - ComplianceRunner service
  - ConfigLoader support
  - ValueObjects (TestResult, TestSuite)

### Changed
- Enhanced core application functionality
- Improved TestCommand implementation
- Updated contracts and interfaces
- Refined output rendering system
- Enhanced service implementations

## [1.0.0](https://github.com/faustbrian/prism/releases/tag/1.0.0) - 2025-12-25

### Added
- Initial release of Prism (originally named Compliance)
- Core testing framework
- Basic command-line interface
- Configuration system
- Output rendering (CI, Detail, Summary)
- Core services (ComplianceRunner, ConfigLoader)
- Value objects (TestResult, TestSuite)
- Docker support for PHP 8.5
- GitHub workflows for quality assurance
- Comprehensive documentation (README, CONTRIBUTING, CODE_OF_CONDUCT, SECURITY)
- ECS code style configuration
- Makefile for common tasks
