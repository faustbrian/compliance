<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Assertions\StrictEqualityAssertion;
use Cline\Prism\Contracts\AssertionInterface;
use Cline\Prism\Output\AssertionRenderer;
use Cline\Prism\Services\CustomAssertionService;

describe('AssertionRenderer', function (): void {
    describe('render()', function (): void {
        test('renders warning message when no custom assertions are registered', function (): void {
            // Arrange
            $service = new CustomAssertionService([]);
            $renderer = new AssertionRenderer();

            // Act
            $result = $renderer->render($service);

            // Assert
            expect($result)->toBe("\n<fg=yellow>No custom assertions registered.</>\n\n");
        });

        test('renders single custom assertion with proper formatting', function (): void {
            // Arrange
            $assertion = new StrictEqualityAssertion();
            $service = new CustomAssertionService(['custom_assertion' => $assertion]);
            $renderer = new AssertionRenderer();

            // Act
            $result = $renderer->render($service);

            // Assert
            expect($result)->toContain('<fg=cyan;options=bold>Available Custom Assertions</>')
                ->and($result)->toContain('Total: 1')
                ->and($result)->toContain('  • custom_assertion')
                ->and($result)->toContain("<fg=gray>Use these assertions in your test files with the 'assertion' field.</>");
        });

        test('renders multiple custom assertions with proper formatting', function (): void {
            // Arrange
            $assertion1 = new StrictEqualityAssertion();
            $assertion2 = new class() implements AssertionInterface
            {
                public function assert(mixed $data, mixed $expected, mixed $actual): bool
                {
                    return true;
                }

                public function getName(): string
                {
                    return 'TestAssertion';
                }

                public function getFailureMessage(mixed $data, mixed $expected, mixed $actual): string
                {
                    return 'Test failure message';
                }
            };
            $assertion3 = new class() implements AssertionInterface
            {
                public function assert(mixed $data, mixed $expected, mixed $actual): bool
                {
                    return false;
                }

                public function getName(): string
                {
                    return 'AnotherAssertion';
                }

                public function getFailureMessage(mixed $data, mixed $expected, mixed $actual): string
                {
                    return 'Another failure message';
                }
            };

            $service = new CustomAssertionService([
                'first_assertion' => $assertion1,
                'second_assertion' => $assertion2,
                'third_assertion' => $assertion3,
            ]);
            $renderer = new AssertionRenderer();

            // Act
            $result = $renderer->render($service);

            // Assert
            expect($result)->toContain('<fg=cyan;options=bold>Available Custom Assertions</>')
                ->and($result)->toContain('Total: 3')
                ->and($result)->toContain('  • first_assertion')
                ->and($result)->toContain('  • second_assertion')
                ->and($result)->toContain('  • third_assertion')
                ->and($result)->toContain("<fg=gray>Use these assertions in your test files with the 'assertion' field.</>");
        });

        test('renders exact output format for empty assertions', function (): void {
            // Arrange
            $service = new CustomAssertionService([]);
            $renderer = new AssertionRenderer();

            // Act
            $result = $renderer->render($service);

            // Assert
            $expected = "\n<fg=yellow>No custom assertions registered.</>\n\n";
            expect($result)->toBe($expected);
        });

        test('renders exact output format for single assertion', function (): void {
            // Arrange
            $assertion = new StrictEqualityAssertion();
            $service = new CustomAssertionService(['test_assertion' => $assertion]);
            $renderer = new AssertionRenderer();

            // Act
            $result = $renderer->render($service);

            // Assert
            $expected = "\n<fg=cyan;options=bold>Available Custom Assertions</>\n\n"
                ."Total: 1\n\n"
                ."  • test_assertion\n"
                ."\n<fg=gray>Use these assertions in your test files with the 'assertion' field.</>\n";
            expect($result)->toBe($expected);
        });

        test('renders exact output format for multiple assertions', function (): void {
            // Arrange
            $assertion1 = new StrictEqualityAssertion();
            $assertion2 = new StrictEqualityAssertion();
            $service = new CustomAssertionService([
                'alpha' => $assertion1,
                'beta' => $assertion2,
            ]);
            $renderer = new AssertionRenderer();

            // Act
            $result = $renderer->render($service);

            // Assert
            $expected = "\n<fg=cyan;options=bold>Available Custom Assertions</>\n\n"
                ."Total: 2\n\n"
                ."  • alpha\n"
                ."  • beta\n"
                ."\n<fg=gray>Use these assertions in your test files with the 'assertion' field.</>\n";
            expect($result)->toBe($expected);
        });

        test('handles assertion names with special characters', function (): void {
            // Arrange
            $assertion = new StrictEqualityAssertion();
            $service = new CustomAssertionService([
                'assertion_with_underscores' => $assertion,
                'assertion-with-dashes' => $assertion,
                'assertion.with.dots' => $assertion,
            ]);
            $renderer = new AssertionRenderer();

            // Act
            $result = $renderer->render($service);

            // Assert
            expect($result)->toContain('  • assertion_with_underscores')
                ->and($result)->toContain('  • assertion-with-dashes')
                ->and($result)->toContain('  • assertion.with.dots')
                ->and($result)->toContain('Total: 3');
        });

        test('preserves order of assertions from service', function (): void {
            // Arrange
            $assertion = new StrictEqualityAssertion();
            $service = new CustomAssertionService([
                'zebra' => $assertion,
                'alpha' => $assertion,
                'middle' => $assertion,
            ]);
            $renderer = new AssertionRenderer();

            // Act
            $result = $renderer->render($service);

            // Assert
            $lines = explode("\n", $result);
            $assertionLines = array_filter($lines, fn ($line): bool => str_starts_with(mb_trim($line), '•'));
            $assertionLines = array_values($assertionLines);

            expect($assertionLines[0])->toContain('zebra')
                ->and($assertionLines[1])->toContain('alpha')
                ->and($assertionLines[2])->toContain('middle');
        });

        test('count matches number of assertions in service', function (): void {
            // Arrange
            $assertion = new StrictEqualityAssertion();
            $service = new CustomAssertionService([
                'one' => $assertion,
                'two' => $assertion,
                'three' => $assertion,
                'four' => $assertion,
                'five' => $assertion,
            ]);
            $renderer = new AssertionRenderer();

            // Act
            $result = $renderer->render($service);

            // Assert
            expect($result)->toContain('Total: 5');
        });

        test('includes header and footer in non-empty output', function (): void {
            // Arrange
            $assertion = new StrictEqualityAssertion();
            $service = new CustomAssertionService(['any_assertion' => $assertion]);
            $renderer = new AssertionRenderer();

            // Act
            $result = $renderer->render($service);

            // Assert
            expect($result)->toStartWith("\n<fg=cyan;options=bold>Available Custom Assertions</>")
                ->and($result)->toEndWith("<fg=gray>Use these assertions in your test files with the 'assertion' field.</>\n");
        });
    });
});
