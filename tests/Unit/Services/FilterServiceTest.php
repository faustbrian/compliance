<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Services\FilterService;
use Cline\Prism\ValueObjects\TestResult;

describe('FilterService', function (): void {
    describe('shouldIncludeFile()', function (): void {
        test('includes all files when no path filter is configured', function (): void {
            // Arrange
            $service = new FilterService();

            // Act
            $result = $service->shouldIncludeFile('tests/unit/ExampleTest.json');

            // Assert
            expect($result)->toBeTrue();
        });

        test('includes file matching glob pattern', function (): void {
            // Arrange
            $service = new FilterService(pathFilter: 'tests/authentication/*.json');

            // Act
            $result = $service->shouldIncludeFile('tests/authentication/login.json');

            // Assert
            expect($result)->toBeTrue();
        });

        test('excludes file not matching glob pattern', function (): void {
            // Arrange
            $service = new FilterService(pathFilter: 'tests/authentication/*.json');

            // Act
            $result = $service->shouldIncludeFile('tests/unit/example.json');

            // Assert
            expect($result)->toBeFalse();
        });

        test('handles wildcard pattern matching', function (): void {
            // Arrange
            $service = new FilterService(pathFilter: '*/authentication/*');

            // Act
            $result = $service->shouldIncludeFile('tests/authentication/login.json');

            // Assert
            expect($result)->toBeTrue();
        });
    });

    describe('shouldIncludeTest()', function (): void {
        test('includes all tests when no filters are configured', function (): void {
            // Arrange
            $service = new FilterService();
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'can login',
                data: ['email' => 'test@example.com'],
                expected: true,
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeTrue();
        });

        test('excludes test matching exclude filter', function (): void {
            // Arrange
            $service = new FilterService(excludeFilter: '/deprecated/i');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'deprecated test case',
                data: [],
                expected: true,
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeFalse();
        });

        test('includes test not matching exclude filter', function (): void {
            // Arrange
            $service = new FilterService(excludeFilter: '/deprecated/i');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'can login',
                data: [],
                expected: true,
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeTrue();
        });

        test('excludes test without matching tag when tag filter is configured', function (): void {
            // Arrange
            $service = new FilterService(tagFilter: 'smoke');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'can login',
                data: [],
                expected: true,
                tags: ['integration', 'auth'],
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeFalse();
        });

        test('includes test with matching tag when tag filter is configured', function (): void {
            // Arrange
            $service = new FilterService(tagFilter: 'smoke');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'can login',
                data: [],
                expected: true,
                tags: ['smoke', 'auth'],
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeTrue();
        });

        test('includes test matching name filter', function (): void {
            // Arrange
            $service = new FilterService(nameFilter: '/user.*login/i');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'can login',
                data: [],
                expected: true,
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeTrue();
        });

        test('excludes test not matching name filter', function (): void {
            // Arrange
            $service = new FilterService(nameFilter: '/admin.*logout/i');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'can login',
                data: [],
                expected: true,
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeFalse();
        });

        test('applies all filters in correct order - exclude filter takes precedence', function (): void {
            // Arrange
            $service = new FilterService(
                nameFilter: '/user/i',
                excludeFilter: '/deprecated/i',
                tagFilter: 'smoke',
            );
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'deprecated user test',
                data: [],
                expected: true,
                tags: ['smoke'],
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeFalse();
        });

        test('applies all filters in correct order - tag filter checked before name filter', function (): void {
            // Arrange
            $service = new FilterService(
                nameFilter: '/user/i',
                tagFilter: 'integration',
            );
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'can login',
                data: [],
                expected: true,
                tags: ['smoke'],
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeFalse();
        });

        test('includes test when all filters pass', function (): void {
            // Arrange
            $service = new FilterService(
                nameFilter: '/user.*login/i',
                excludeFilter: '/deprecated/i',
                tagFilter: 'smoke',
            );
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'can login successfully',
                data: [],
                expected: true,
                tags: ['smoke', 'auth'],
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeTrue();
        });

        test('constructs test name correctly from group and description', function (): void {
            // Arrange
            $service = new FilterService(nameFilter: '/^Authentication - validates credentials$/');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'Authentication',
                description: 'validates credentials',
                data: [],
                expected: true,
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeTrue();
        });
    });

    describe('filterResults()', function (): void {
        test('returns all results when no filters are configured', function (): void {
            // Arrange
            $service = new FilterService();
            $results = [
                TestResult::pass('test:1', 'test.json', 'User', 'can login', [], true),
                TestResult::pass('test:2', 'test.json', 'User', 'can logout', [], true),
                TestResult::pass('test:3', 'test.json', 'Admin', 'can manage users', [], true),
            ];

            // Act
            $filtered = $service->filterResults($results);

            // Assert
            expect($filtered)->toHaveCount(3)
                ->and($filtered)->toBe($results);
        });

        test('filters results by name pattern', function (): void {
            // Arrange
            $service = new FilterService(nameFilter: '/user.*login/i');
            $results = [
                TestResult::pass('test:1', 'test.json', 'User', 'can login', [], true),
                TestResult::pass('test:2', 'test.json', 'User', 'can logout', [], true),
                TestResult::pass('test:3', 'test.json', 'Admin', 'can manage users', [], true),
            ];

            // Act
            $filtered = $service->filterResults($results);

            // Assert
            expect($filtered)->toHaveCount(1)
                ->and($filtered[0]->description)->toBe('can login');
        });

        test('filters results by exclude pattern', function (): void {
            // Arrange
            $service = new FilterService(excludeFilter: '/deprecated/i');
            $results = [
                TestResult::pass('test:1', 'test.json', 'User', 'can login', [], true),
                TestResult::pass('test:2', 'test.json', 'User', 'deprecated test', [], true),
                TestResult::pass('test:3', 'test.json', 'Admin', 'can manage users', [], true),
            ];

            // Act
            $filtered = $service->filterResults($results);

            // Assert
            expect($filtered)->toHaveCount(2)
                ->and($filtered[0]->description)->toBe('can login')
                ->and($filtered[1]->description)->toBe('can manage users');
        });

        test('filters results by tag', function (): void {
            // Arrange
            $service = new FilterService(tagFilter: 'smoke');
            $results = [
                TestResult::pass('test:1', 'test.json', 'User', 'can login', [], true, tags: ['smoke']),
                TestResult::pass('test:2', 'test.json', 'User', 'can logout', [], true, tags: ['integration']),
                TestResult::pass('test:3', 'test.json', 'Admin', 'can manage', [], true, tags: ['smoke', 'admin']),
            ];

            // Act
            $filtered = $service->filterResults($results);

            // Assert
            expect($filtered)->toHaveCount(2)
                ->and($filtered[0]->description)->toBe('can login')
                ->and($filtered[1]->description)->toBe('can manage');
        });

        test('applies multiple filters and returns intersection', function (): void {
            // Arrange
            $service = new FilterService(
                nameFilter: '/^User/i',
                excludeFilter: '/deprecated/i',
                tagFilter: 'smoke',
            );
            $results = [
                TestResult::pass('test:1', 'test.json', 'User', 'can login', [], true, tags: ['smoke']),
                TestResult::pass('test:2', 'test.json', 'User', 'deprecated test', [], true, tags: ['smoke']),
                TestResult::pass('test:3', 'test.json', 'Admin', 'can manage users', [], true, tags: ['smoke']),
                TestResult::pass('test:4', 'test.json', 'User', 'can logout', [], true, tags: ['integration']),
            ];

            // Act
            $filtered = $service->filterResults($results);

            // Assert
            expect($filtered)->toHaveCount(1)
                ->and($filtered[0]->description)->toBe('can login');
        });

        test('returns empty array when no results match filters', function (): void {
            // Arrange
            $service = new FilterService(nameFilter: '/nonexistent/');
            $results = [
                TestResult::pass('test:1', 'test.json', 'User', 'can login', [], true),
                TestResult::pass('test:2', 'test.json', 'User', 'can logout', [], true),
            ];

            // Act
            $filtered = $service->filterResults($results);

            // Assert
            expect($filtered)->toBeEmpty();
        });

        test('maintains sequential integer keys in filtered results', function (): void {
            // Arrange
            $service = new FilterService(nameFilter: '/login|logout/');
            $results = [
                TestResult::pass('test:1', 'test.json', 'User', 'can login', [], true),
                TestResult::pass('test:2', 'test.json', 'User', 'can register', [], true),
                TestResult::pass('test:3', 'test.json', 'User', 'can logout', [], true),
                TestResult::pass('test:4', 'test.json', 'User', 'can delete', [], true),
            ];

            // Act
            $filtered = $service->filterResults($results);

            // Assert
            expect($filtered)->toHaveCount(2)
                ->and(array_keys($filtered))->toBe([0, 1])
                ->and($filtered[0]->description)->toBe('can login')
                ->and($filtered[1]->description)->toBe('can logout');
        });

        test('handles empty results array', function (): void {
            // Arrange
            $service = new FilterService(nameFilter: '/user/');

            // Act
            $filtered = $service->filterResults([]);

            // Assert
            expect($filtered)->toBeEmpty();
        });
    });

    describe('edge cases', function (): void {
        test('handles case-insensitive name matching', function (): void {
            // Arrange
            $service = new FilterService(nameFilter: '/USER/i');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'user',
                description: 'can login',
                data: [],
                expected: true,
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeTrue();
        });

        test('handles special regex characters in patterns', function (): void {
            // Arrange
            $service = new FilterService(nameFilter: '/Test - \[feature\]/');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'Test',
                description: '[feature] implementation',
                data: [],
                expected: true,
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeTrue();
        });

        test('handles empty tag array', function (): void {
            // Arrange
            $service = new FilterService(tagFilter: 'smoke');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'can login',
                data: [],
                expected: true,
                tags: [],
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeFalse();
        });

        test('handles complex glob patterns with multiple wildcards', function (): void {
            // Arrange
            $service = new FilterService(pathFilter: '**/authentication/**/*.json');

            // Act
            $result = $service->shouldIncludeFile('tests/integration/authentication/api/login.json');

            // Assert
            expect($result)->toBeTrue();
        });

        test('handles absolute file paths in glob matching', function (): void {
            // Arrange
            $service = new FilterService(pathFilter: '/var/www/tests/*.json');

            // Act
            $result = $service->shouldIncludeFile('/var/www/tests/example.json');

            // Assert
            expect($result)->toBeTrue();
        });

        test('uses strict comparison for tag matching', function (): void {
            // Arrange
            $service = new FilterService(tagFilter: '1');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'test',
                data: [],
                expected: true,
                tags: [1, '1'],
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeTrue();
        });

        test('handles failed test results with errors', function (): void {
            // Arrange
            $service = new FilterService(nameFilter: '/User/');
            $result = TestResult::fail(
                id: 'test:1',
                file: 'test.json',
                group: 'User',
                description: 'validation test',
                data: [],
                expected: true,
                actual: false,
                error: 'Validation failed',
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeTrue();
        });

        test('filters work with complex test data', function (): void {
            // Arrange
            $service = new FilterService(nameFilter: '/Nested/');
            $result = TestResult::pass(
                id: 'test:1',
                file: 'test.json',
                group: 'Nested',
                description: 'data structure',
                data: ['complex' => ['nested' => ['value' => 123]]],
                expected: true,
            );

            // Act
            $shouldInclude = $service->shouldIncludeTest($result);

            // Assert
            expect($shouldInclude)->toBeTrue();
        });
    });
});
