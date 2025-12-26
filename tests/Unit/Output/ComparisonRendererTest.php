<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Output\ComparisonRenderer;

describe('ComparisonRenderer', function (): void {
    test('renders error message when error is present in comparison data', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'error' => 'Failed to compare validators',
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('<fg=red>Failed to compare validators</>')
            ->and($output)->toContain("\n");
    });

    test('renders success message when no discrepancies found', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['validator1', 'validator2'],
            'total_tests' => 10,
            'discrepancies_count' => 0,
            'discrepancies' => [],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('Validator Comparison Report')
            ->and($output)->toContain('Validators: validator1, validator2')
            ->and($output)->toContain('Total Tests: 10')
            ->and($output)->toContain('Discrepancies: 0')
            ->and($output)->toContain('<fg=green>✓ All validators produced identical results!</>');
    });

    test('renders discrepancies when validators disagree', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['validator1', 'validator2'],
            'total_tests' => 10,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-001',
                    'description' => 'Email validation test',
                    'agreement' => '50%',
                    'outcomes' => [
                        'validator1' => [
                            'passed' => true,
                            'actual' => true,
                            'expected' => true,
                        ],
                        'validator2' => [
                            'passed' => false,
                            'actual' => false,
                            'expected' => true,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('Validator Comparison Report')
            ->and($output)->toContain('Validators: validator1, validator2')
            ->and($output)->toContain('Total Tests: 10')
            ->and($output)->toContain('Discrepancies: 1')
            ->and($output)->toContain('Found 1 test(s) with differing results:')
            ->and($output)->toContain('1. test-001')
            ->and($output)->toContain('Description: Email validation test')
            ->and($output)->toContain('Agreement: 50%')
            ->and($output)->toContain('validator1: <fg=green>PASS</> (expected: valid, got: valid)')
            ->and($output)->toContain('validator2: <fg=red>FAIL</> (expected: valid, got: invalid)');
    });

    test('renders multiple discrepancies correctly', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['v1', 'v2', 'v3'],
            'total_tests' => 20,
            'discrepancies_count' => 2,
            'discrepancies' => [
                [
                    'test_id' => 'test-001',
                    'description' => 'First test',
                    'agreement' => '33%',
                    'outcomes' => [
                        'v1' => [
                            'passed' => true,
                            'actual' => true,
                            'expected' => true,
                        ],
                    ],
                ],
                [
                    'test_id' => 'test-002',
                    'description' => 'Second test',
                    'agreement' => '66%',
                    'outcomes' => [
                        'v2' => [
                            'passed' => false,
                            'actual' => false,
                            'expected' => false,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('Found 2 test(s) with differing results:')
            ->and($output)->toContain('1. test-001')
            ->and($output)->toContain('Description: First test')
            ->and($output)->toContain('Agreement: 33%')
            ->and($output)->toContain('2. test-002')
            ->and($output)->toContain('Description: Second test')
            ->and($output)->toContain('Agreement: 66%');
    });

    test('handles all boolean combinations for outcome status', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['v1', 'v2', 'v3', 'v4'],
            'total_tests' => 4,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-boolean-combos',
                    'description' => 'All boolean combinations',
                    'agreement' => '25%',
                    'outcomes' => [
                        'v1' => [
                            'passed' => true,
                            'actual' => true,
                            'expected' => true,
                        ],
                        'v2' => [
                            'passed' => false,
                            'actual' => false,
                            'expected' => false,
                        ],
                        'v3' => [
                            'passed' => true,
                            'actual' => false,
                            'expected' => false,
                        ],
                        'v4' => [
                            'passed' => false,
                            'actual' => true,
                            'expected' => false,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('v1: <fg=green>PASS</> (expected: valid, got: valid)')
            ->and($output)->toContain('v2: <fg=red>FAIL</> (expected: invalid, got: invalid)')
            ->and($output)->toContain('v3: <fg=green>PASS</> (expected: invalid, got: invalid)')
            ->and($output)->toContain('v4: <fg=red>FAIL</> (expected: invalid, got: valid)');
    });

    test('handles missing optional keys with defaults', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('Validator Comparison Report')
            ->and($output)->toContain('Validators: ')
            ->and($output)->toContain('Total Tests: 0')
            ->and($output)->toContain('Discrepancies: 0')
            ->and($output)->toContain('<fg=green>✓ All validators produced identical results!</>');
    });

    test('handles discrepancy with missing optional keys', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['validator1'],
            'total_tests' => 1,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    // Missing test_id, description, agreement, outcomes
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('1. unknown')
            ->and($output)->toContain('Description: No description')
            ->and($output)->toContain('Agreement: 0%')
            ->and($output)->toContain('Results:');
    });

    test('handles outcome with missing optional boolean keys', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['validator1'],
            'total_tests' => 1,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-missing-bools',
                    'description' => 'Test with missing booleans',
                    'agreement' => '100%',
                    'outcomes' => [
                        'validator1' => [
                            // Missing passed, actual, expected
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('validator1: <fg=red>FAIL</> (expected: invalid, got: invalid)');
    });

    test('handles empty validators array', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => [],
            'total_tests' => 0,
            'discrepancies_count' => 0,
            'discrepancies' => [],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('Validators: ')
            ->and($output)->toContain('Total Tests: 0')
            ->and($output)->toContain('Discrepancies: 0');
    });

    test('handles empty outcomes array in discrepancy', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['validator1'],
            'total_tests' => 1,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-empty-outcomes',
                    'description' => 'Test with no outcomes',
                    'agreement' => '0%',
                    'outcomes' => [],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('1. test-empty-outcomes')
            ->and($output)->toContain('Description: Test with no outcomes')
            ->and($output)->toContain('Agreement: 0%')
            ->and($output)->toContain('Results:');
    });

    test('handles large number of discrepancies', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $discrepancies = [];

        for ($i = 1; $i <= 10; ++$i) {
            $discrepancies[] = [
                'test_id' => 'test-'.$i,
                'description' => 'Test number '.$i,
                'agreement' => '50%',
                'outcomes' => [
                    'v1' => [
                        'passed' => true,
                        'actual' => true,
                        'expected' => true,
                    ],
                ],
            ];
        }

        $comparisonData = [
            'validators' => ['v1', 'v2'],
            'total_tests' => 20,
            'discrepancies_count' => 10,
            'discrepancies' => $discrepancies,
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('Found 10 test(s) with differing results:')
            ->and($output)->toContain('1. test-1')
            ->and($output)->toContain('10. test-10');
    });

    test('formats output with proper spacing and separators', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['v1'],
            'total_tests' => 1,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-spacing',
                    'description' => 'Test spacing',
                    'agreement' => '100%',
                    'outcomes' => [
                        'v1' => [
                            'passed' => true,
                            'actual' => true,
                            'expected' => true,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain("\n")
            ->and($output)->toContain('   ')
            ->and($output)->toContain('     ');
    });

    test('handles mixed valid and invalid expectations', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['v1', 'v2'],
            'total_tests' => 2,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-mixed',
                    'description' => 'Mixed expectations',
                    'agreement' => '50%',
                    'outcomes' => [
                        'v1' => [
                            'passed' => true,
                            'actual' => true,
                            'expected' => true,
                        ],
                        'v2' => [
                            'passed' => true,
                            'actual' => false,
                            'expected' => false,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('v1: <fg=green>PASS</> (expected: valid, got: valid)')
            ->and($output)->toContain('v2: <fg=green>PASS</> (expected: invalid, got: invalid)');
    });

    test('handles passed true with actual false and expected false', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['v1'],
            'total_tests' => 1,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-pass-invalid',
                    'description' => 'Pass with invalid',
                    'agreement' => '100%',
                    'outcomes' => [
                        'v1' => [
                            'passed' => true,
                            'actual' => false,
                            'expected' => false,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('v1: <fg=green>PASS</> (expected: invalid, got: invalid)');
    });

    test('handles passed false with actual true and expected true', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['v1'],
            'total_tests' => 1,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-fail-valid',
                    'description' => 'Fail with valid',
                    'agreement' => '100%',
                    'outcomes' => [
                        'v1' => [
                            'passed' => false,
                            'actual' => true,
                            'expected' => true,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('v1: <fg=red>FAIL</> (expected: valid, got: valid)');
    });

    test('handles passed false with actual false and expected true', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['v1'],
            'total_tests' => 1,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-fail-mismatch',
                    'description' => 'Fail with mismatch',
                    'agreement' => '100%',
                    'outcomes' => [
                        'v1' => [
                            'passed' => false,
                            'actual' => false,
                            'expected' => true,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('v1: <fg=red>FAIL</> (expected: valid, got: invalid)');
    });

    test('handles passed false with actual true and expected false', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['v1'],
            'total_tests' => 1,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-fail-reversed',
                    'description' => 'Fail with reversed expectation',
                    'agreement' => '100%',
                    'outcomes' => [
                        'v1' => [
                            'passed' => false,
                            'actual' => true,
                            'expected' => false,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('v1: <fg=red>FAIL</> (expected: invalid, got: valid)');
    });

    test('handles passed true with actual true and expected false', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['v1'],
            'total_tests' => 1,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-pass-unexpected',
                    'description' => 'Pass with unexpected valid',
                    'agreement' => '100%',
                    'outcomes' => [
                        'v1' => [
                            'passed' => true,
                            'actual' => true,
                            'expected' => false,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('v1: <fg=green>PASS</> (expected: invalid, got: valid)');
    });

    test('handles passed true with actual false and expected true', function (): void {
        // Arrange
        $renderer = new ComparisonRenderer();
        $comparisonData = [
            'validators' => ['v1'],
            'total_tests' => 1,
            'discrepancies_count' => 1,
            'discrepancies' => [
                [
                    'test_id' => 'test-pass-unexpected-invalid',
                    'description' => 'Pass with unexpected invalid',
                    'agreement' => '100%',
                    'outcomes' => [
                        'v1' => [
                            'passed' => true,
                            'actual' => false,
                            'expected' => true,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $output = $renderer->render($comparisonData);

        // Assert
        expect($output)->toContain('v1: <fg=green>PASS</> (expected: valid, got: invalid)');
    });
});
