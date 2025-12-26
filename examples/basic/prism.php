<?php

declare(strict_types=1);

use Cline\Prism\Contracts\PrismTestInterface;
use Cline\Prism\ValueObjects\ValidationResult;

return [
    new class implements PrismTestInterface
    {
        public function getName(): string
        {
            return 'basic-validation';
        }

        public function getTestDirectory(): string
        {
            return __DIR__.'/tests';
        }

        public function validate(mixed $data, mixed $schema): ValidationResult
        {
            // Simple schema validation implementation
            // In a real implementation, you would use a JSON Schema validator library

            if (!is_array($schema)) {
                return ValidationResult::invalid(['Schema must be an array']);
            }

            $errors = [];

            // Check required fields
            if (isset($schema['required']) && is_array($schema['required'])) {
                foreach ($schema['required'] as $field) {
                    if (!is_array($data) || !array_key_exists($field, $data)) {
                        $errors[] = "Required field '{$field}' is missing";
                    }
                }
            }

            // Check type validation
            if (isset($schema['properties']) && is_array($schema['properties']) && is_array($data)) {
                foreach ($schema['properties'] as $field => $fieldSchema) {
                    if (!array_key_exists($field, $data)) {
                        continue;
                    }

                    $value = $data[$field];
                    $type = $fieldSchema['type'] ?? null;

                    if ($type === 'string' && !is_string($value)) {
                        $errors[] = "Field '{$field}' must be a string";
                    }

                    if ($type === 'number' && !is_numeric($value)) {
                        $errors[] = "Field '{$field}' must be a number";
                    }

                    if ($type === 'boolean' && !is_bool($value)) {
                        $errors[] = "Field '{$field}' must be a boolean";
                    }

                    // Email format validation
                    if (isset($fieldSchema['format']) && $fieldSchema['format'] === 'email') {
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[] = "Field '{$field}' must be a valid email";
                        }
                    }

                    // Minimum/maximum validation
                    if (isset($fieldSchema['minimum']) && is_numeric($value)) {
                        if ($value < $fieldSchema['minimum']) {
                            $errors[] = "Field '{$field}' must be at least {$fieldSchema['minimum']}";
                        }
                    }

                    if (isset($fieldSchema['maximum']) && is_numeric($value)) {
                        if ($value > $fieldSchema['maximum']) {
                            $errors[] = "Field '{$field}' must be at most {$fieldSchema['maximum']}";
                        }
                    }
                }
            }

            return $errors === [] ? ValidationResult::valid() : ValidationResult::invalid($errors);
        }

        public function decodeJson(string $json): mixed
        {
            return json_decode($json, true);
        }

        public function shouldIncludeFile(string $filePath): bool
        {
            return true;
        }
    },
];
