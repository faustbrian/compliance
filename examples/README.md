# Prism Examples

This directory contains example configurations demonstrating various Prism features.

## Available Examples

### Basic Validation (`basic/`)

Demonstrates fundamental JSON schema validation:
- Required field validation
- Type checking (string, number, boolean)
- Format validation (email)
- Range constraints (minimum/maximum)

```bash
cd basic && vendor/bin/prism
```

### Custom Assertions (`custom-assertions/`)

Shows how to implement custom assertion logic beyond simple expected/actual equality:
- Domain-specific validation rules
- Business logic assertions
- Complex validation scenarios

```bash
cd custom-assertions && vendor/bin/prism
```

### Filtering (`filtering/`)

Demonstrates filtering and tagging features:
- Running specific test files with `--path-filter`
- Running tests by name with `--filter`
- Tag-based test selection with `--tag`
- Excluding tests with `--exclude`

```bash
cd filtering && vendor/bin/prism --tag happy-path
cd filtering && vendor/bin/prism --filter "email validation"
```

### Comparison (`comparison/`)

Shows validator comparison capabilities:
- Running multiple validators on same test data
- Identifying discrepancies between validators
- Generating compliance reports

```bash
cd comparison && vendor/bin/prism --compare
```

## Common Commands

All examples support these flags:

- `--verbose` - Show each test as it runs
- `--failures` - Display detailed failure information with JSON diff
- `--format json` - Output results as JSON
- `--format xml` - Output results as JUnit XML (for CI/CD)
- `--version` - Display Prism version
- `--parallel 4` - Run tests in parallel with 4 workers

## Learning Path

1. Start with `basic/` to understand core concepts
2. Explore `filtering/` to learn test organization
3. Try `custom-assertions/` for advanced validation
4. Use `comparison/` for multi-validator scenarios
