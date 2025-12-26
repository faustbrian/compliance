<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Output\SnapshotRenderer;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;

describe('SnapshotRenderer', function (): void {
    test('renders suite not found message when suite does not exist', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Different Suite',
                results: [],
                duration: 1.0,
            ),
        ];
        $snapshotData = ['results' => []];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'NonExistent Suite');

        // Assert
        expect($output)->toContain('Snapshot Comparison - NonExistent Suite');
        expect($output)->toContain('Suite not found in current results.');
    });

    test('renders no changes detected when all tests match snapshot', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('test-1', 'file.json', 'group', 'desc1', [], true),
                    TestResult::fail('test-2', 'file.json', 'group', 'desc2', [], true, false),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = [
            'results' => [
                'test-1' => ['passed' => true],
                'test-2' => ['passed' => false],
            ],
        ];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('Snapshot Comparison - Test Suite');
        expect($output)->toContain('✓ No changes detected - all tests match snapshot');
        expect($output)->not->toContain('Changed Tests');
        expect($output)->not->toContain('New Tests');
        expect($output)->not->toContain('Missing Tests');
    });

    test('detects test that changed from PASS to FAIL', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::fail('test-1', 'file.json', 'group', 'desc', [], true, false),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = [
            'results' => [
                'test-1' => ['passed' => true],
            ],
        ];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('Changed Tests (1):');
        expect($output)->toContain('test-1');
        expect($output)->toContain('PASS → FAIL');
        expect($output)->toContain('⚠ Snapshot mismatch detected! Use --update-snapshots to update.');
    });

    test('detects test that changed from FAIL to PASS', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('test-1', 'file.json', 'group', 'desc', [], true),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = [
            'results' => [
                'test-1' => ['passed' => false],
            ],
        ];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('Changed Tests (1):');
        expect($output)->toContain('test-1');
        expect($output)->toContain('FAIL → PASS');
        expect($output)->toContain('⚠ Snapshot mismatch detected! Use --update-snapshots to update.');
    });

    test('detects multiple changed tests', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('test-1', 'file.json', 'group', 'desc1', [], true),
                    TestResult::fail('test-2', 'file.json', 'group', 'desc2', [], true, false),
                    TestResult::pass('test-3', 'file.json', 'group', 'desc3', [], true),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = [
            'results' => [
                'test-1' => ['passed' => false],
                'test-2' => ['passed' => true],
                'test-3' => ['passed' => false],
            ],
        ];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('Changed Tests (3):');
        expect($output)->toContain('test-1');
        expect($output)->toContain('FAIL → PASS');
        expect($output)->toContain('test-2');
        expect($output)->toContain('PASS → FAIL');
        expect($output)->toContain('test-3');
    });

    test('detects new tests not in snapshot', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('test-1', 'file.json', 'group', 'desc1', [], true),
                    TestResult::pass('test-2', 'file.json', 'group', 'desc2', [], true),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = [
            'results' => [
                'test-1' => ['passed' => true],
            ],
        ];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('New Tests (1):');
        expect($output)->toContain('test-2');
        expect($output)->toContain('⚠ Snapshot mismatch detected! Use --update-snapshots to update.');
    });

    test('detects multiple new tests', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('test-1', 'file.json', 'group', 'desc1', [], true),
                    TestResult::pass('test-2', 'file.json', 'group', 'desc2', [], true),
                    TestResult::pass('test-3', 'file.json', 'group', 'desc3', [], true),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = [
            'results' => [
                'test-1' => ['passed' => true],
            ],
        ];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('New Tests (2):');
        expect($output)->toContain('test-2');
        expect($output)->toContain('test-3');
    });

    test('detects missing tests that are in snapshot but not in current results', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('test-1', 'file.json', 'group', 'desc1', [], true),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = [
            'results' => [
                'test-1' => ['passed' => true],
                'test-2' => ['passed' => false],
            ],
        ];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('Missing Tests (1):');
        expect($output)->toContain('test-2');
        expect($output)->toContain('⚠ Snapshot mismatch detected! Use --update-snapshots to update.');
    });

    test('detects multiple missing tests', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('test-1', 'file.json', 'group', 'desc1', [], true),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = [
            'results' => [
                'test-1' => ['passed' => true],
                'test-2' => ['passed' => false],
                'test-3' => ['passed' => true],
            ],
        ];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('Missing Tests (2):');
        expect($output)->toContain('test-2');
        expect($output)->toContain('test-3');
    });

    test('detects all types of changes simultaneously', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('test-1', 'file.json', 'group', 'desc1', [], true),
                    TestResult::fail('test-2', 'file.json', 'group', 'desc2', [], true, false),
                    TestResult::pass('test-new', 'file.json', 'group', 'desc-new', [], true),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = [
            'results' => [
                'test-1' => ['passed' => true],
                'test-2' => ['passed' => true],
                'test-missing' => ['passed' => false],
            ],
        ];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('Changed Tests (1):');
        expect($output)->toContain('test-2');
        expect($output)->toContain('PASS → FAIL');
        expect($output)->toContain('New Tests (1):');
        expect($output)->toContain('test-new');
        expect($output)->toContain('Missing Tests (1):');
        expect($output)->toContain('test-missing');
        expect($output)->toContain('⚠ Snapshot mismatch detected! Use --update-snapshots to update.');
    });

    test('handles empty snapshot data', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('test-1', 'file.json', 'group', 'desc1', [], true),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = [];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('New Tests (1):');
        expect($output)->toContain('test-1');
    });

    test('handles snapshot without results key', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('test-1', 'file.json', 'group', 'desc1', [], true),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = ['other_key' => 'value'];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('New Tests (1):');
        expect($output)->toContain('test-1');
    });

    test('handles empty suite results', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [],
                duration: 1.0,
            ),
        ];
        $snapshotData = [
            'results' => [
                'test-1' => ['passed' => true],
            ],
        ];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('Missing Tests (1):');
        expect($output)->toContain('test-1');
    });

    test('handles both empty suite results and empty snapshot', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [],
                duration: 1.0,
            ),
        ];
        $snapshotData = ['results' => []];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('✓ No changes detected - all tests match snapshot');
    });

    test('renders suite name in header', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'My Custom Suite Name',
                results: [],
                duration: 1.0,
            ),
        ];
        $snapshotData = ['results' => []];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'My Custom Suite Name');

        // Assert
        expect($output)->toContain('Snapshot Comparison - My Custom Suite Name');
    });

    test('output ends with newline', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [],
                duration: 1.0,
            ),
        ];
        $snapshotData = ['results' => []];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toEndWith("\n");
    });

    test('handles test with same id appearing multiple times is processed correctly', function (): void {
        // Arrange
        $suites = [
            new TestSuite(
                name: 'Test Suite',
                results: [
                    TestResult::pass('test-1', 'file.json', 'group', 'desc1', [], true),
                    TestResult::pass('test-1', 'file.json', 'group', 'desc2', [], true),
                ],
                duration: 1.0,
            ),
        ];
        $snapshotData = [
            'results' => [
                'test-1' => ['passed' => true],
            ],
        ];
        $renderer = new SnapshotRenderer();

        // Act
        $output = $renderer->render($suites, $snapshotData, 'Test Suite');

        // Assert
        expect($output)->toContain('✓ No changes detected - all tests match snapshot');
    });
});
