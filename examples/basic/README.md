# Basic Prism Example

This example demonstrates basic JSON Schema validation using Prism.

## Files

- `prism.php` - Configuration file defining the test instance
- `tests/validation.json` - Test cases for user validation

## Running Tests

```bash
# Run all tests
vendor/bin/prism

# Run with verbose output
vendor/bin/prism --verbose

# Show failures with detailed diff
vendor/bin/prism --failures
```

## Test Structure

Each test file contains groups of related tests:

```json
[
  {
    "description": "User validation",
    "schema": { ... },
    "tests": [
      {
        "description": "valid user",
        "data": { ... },
        "valid": true
      }
    ]
  }
]
```

## What This Example Tests

- Required field validation
- Type validation (string, number, boolean)
- Format validation (email)
- Minimum/maximum constraints
- Pattern matching (regex)
