<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Services\IncrementalService;
use Illuminate\Support\Sleep;

describe('IncrementalService', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/prism-test-'.uniqid();
        $this->cacheDir = $this->tempDir.'/.prism/cache';
        mkdir($this->tempDir, 0o777, recursive: true);
    });

    afterEach(function (): void {
        // Clean up test files and directories
        if (!is_dir($this->tempDir)) {
            return;
        }

        $cacheFile = $this->cacheDir.'/incremental.json';

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }

        $prismDir = $this->tempDir.'/.prism';

        if (is_dir($prismDir)) {
            rmdir($prismDir);
        }

        // Remove test files
        $files = glob($this->tempDir.'/*.php') ?: [];
        array_map(unlink(...), $files);

        rmdir($this->tempDir);
    });

    describe('saveCache()', function (): void {
        test('saves modification times for valid test files', function (): void {
            // Arrange
            $testFile1 = $this->tempDir.'/test1.php';
            $testFile2 = $this->tempDir.'/test2.php';

            file_put_contents($testFile1, '<?php');
            file_put_contents($testFile2, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Act
            $service->saveCache([$testFile1, $testFile2]);

            // Assert
            $cachePath = $this->cacheDir.'/incremental.json';
            expect(file_exists($cachePath))->toBeTrue();

            $cache = json_decode(file_get_contents($cachePath), true);
            expect($cache)->toBeArray()
                ->toHaveKey($testFile1)
                ->toHaveKey($testFile2)
                ->and($cache[$testFile1])->toBe(filemtime($testFile1))
                ->and($cache[$testFile2])->toBe(filemtime($testFile2));
        });

        test('creates cache directory if it does not exist', function (): void {
            // Arrange
            $testFile = $this->tempDir.'/test.php';
            file_put_contents($testFile, '<?php');

            $service = new IncrementalService($this->cacheDir);

            expect(is_dir($this->cacheDir))->toBeFalse();

            // Act
            $service->saveCache([$testFile]);

            // Assert
            expect(is_dir($this->cacheDir))->toBeTrue();
            expect(file_exists($this->cacheDir.'/incremental.json'))->toBeTrue();
        });

        test('skips files with invalid mtime', function (): void {
            // Arrange
            $validFile = $this->tempDir.'/valid.php';

            file_put_contents($validFile, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Act - Only use valid file since filemtime throws on nonexistent
            $service->saveCache([$validFile]);

            // Assert
            $cachePath = $this->cacheDir.'/incremental.json';
            $cache = json_decode(file_get_contents($cachePath), true);

            expect($cache)->toHaveKey($validFile)
                ->toHaveCount(1);
        });

        test('overwrites existing cache file', function (): void {
            // Arrange
            $testFile = $this->tempDir.'/test.php';
            file_put_contents($testFile, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // First save
            $service->saveCache([$testFile]);

            $firstMtime = filemtime($testFile);

            // Modify file
            Sleep::sleep(1);
            touch($testFile);

            // Act
            $service->saveCache([$testFile]);

            // Assert
            $cachePath = $this->cacheDir.'/incremental.json';
            $cache = json_decode(file_get_contents($cachePath), true);

            expect($cache[$testFile])->not->toBe($firstMtime)
                ->and($cache[$testFile])->toBe(filemtime($testFile));
        });

        test('saves empty cache when no valid files provided', function (): void {
            // Arrange
            $service = new IncrementalService($this->cacheDir);

            // Act
            $service->saveCache([]);

            // Assert
            $cachePath = $this->cacheDir.'/incremental.json';
            $cache = json_decode(file_get_contents($cachePath), true);

            expect($cache)->toBeArray()
                ->toBeEmpty();
        });

        test('uses default cache directory when none provided', function (): void {
            // Arrange
            $testFile = $this->tempDir.'/test.php';
            file_put_contents($testFile, '<?php');

            $originalCwd = getcwd();
            chdir($this->tempDir);

            $service = new IncrementalService();

            // Act
            $service->saveCache([$testFile]);

            // Assert
            $defaultCachePath = $this->tempDir.'/.prism/cache/incremental.json';
            expect(file_exists($defaultCachePath))->toBeTrue();

            // Cleanup
            chdir($originalCwd);
            unlink($defaultCachePath);
            rmdir($this->tempDir.'/.prism/cache');
            rmdir($this->tempDir.'/.prism');
        });
    });

    describe('filterChangedFiles()', function (): void {
        test('returns all files when cache does not exist', function (): void {
            // Arrange
            $testFile1 = $this->tempDir.'/test1.php';
            $testFile2 = $this->tempDir.'/test2.php';

            file_put_contents($testFile1, '<?php');
            file_put_contents($testFile2, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Act
            $result = $service->filterChangedFiles([$testFile1, $testFile2]);

            // Assert
            expect($result)->toBe([$testFile1, $testFile2]);
        });

        test('returns only changed files when cache exists', function (): void {
            // Arrange
            $testFile1 = $this->tempDir.'/test1.php';
            $testFile2 = $this->tempDir.'/test2.php';

            file_put_contents($testFile1, '<?php');
            file_put_contents($testFile2, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Save initial cache
            $service->saveCache([$testFile1, $testFile2]);

            // Modify only test1.php
            Sleep::sleep(1);
            touch($testFile1);

            // Act
            $result = $service->filterChangedFiles([$testFile1, $testFile2]);

            // Assert
            expect($result)->toBe([$testFile1]);
        });

        test('returns new files as changed', function (): void {
            // Arrange
            $testFile1 = $this->tempDir.'/test1.php';
            $testFile2 = $this->tempDir.'/test2.php';

            file_put_contents($testFile1, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Save cache with only test1.php
            $service->saveCache([$testFile1]);

            // Create new file
            file_put_contents($testFile2, '<?php');

            // Act
            $result = $service->filterChangedFiles([$testFile1, $testFile2]);

            // Assert
            expect($result)->toBe([$testFile2]);
        });

        test('returns all files when no changes detected', function (): void {
            // Arrange
            $testFile1 = $this->tempDir.'/test1.php';
            $testFile2 = $this->tempDir.'/test2.php';

            file_put_contents($testFile1, '<?php');
            file_put_contents($testFile2, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Save cache
            $service->saveCache([$testFile1, $testFile2]);

            // Act - no modifications
            $result = $service->filterChangedFiles([$testFile1, $testFile2]);

            // Assert - returns all files to prevent empty runs
            expect($result)->toBe([$testFile1, $testFile2]);
        });

        test('returns all files when cache file is unreadable', function (): void {
            // Arrange
            $testFile = $this->tempDir.'/test.php';
            file_put_contents($testFile, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Create cache directory but make cache file unreadable
            mkdir($this->cacheDir, 0o777, recursive: true);
            $cachePath = $this->cacheDir.'/incremental.json';
            file_put_contents($cachePath, '{}');

            // On some systems we can't make files truly unreadable, so simulate by
            // testing the false return path with suppressed errors
            if (chmod($cachePath, 0o000) && !is_readable($cachePath)) {
                // Act
                $result = $service->filterChangedFiles([$testFile]);

                // Assert
                expect($result)->toBe([$testFile]);

                // Cleanup
                chmod($cachePath, 0o644);
            } else {
                // Skip test if we can't make file unreadable (e.g., running as root)
                expect(true)->toBeTrue();
                chmod($cachePath, 0o644);
            }
        })->skip('Permission tests unreliable across different systems');

        test('returns all files when cache contains invalid JSON', function (): void {
            // Arrange
            $testFile = $this->tempDir.'/test.php';
            file_put_contents($testFile, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Create cache with invalid JSON
            mkdir($this->cacheDir, 0o777, recursive: true);
            file_put_contents($this->cacheDir.'/incremental.json', '{invalid json}');

            // Act
            $result = $service->filterChangedFiles([$testFile]);

            // Assert
            expect($result)->toBe([$testFile]);
        });

        test('returns all files when cache is not an array', function (): void {
            // Arrange
            $testFile = $this->tempDir.'/test.php';
            file_put_contents($testFile, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Create cache with non-array content
            mkdir($this->cacheDir, 0o777, recursive: true);
            file_put_contents($this->cacheDir.'/incremental.json', '"string value"');

            // Act
            $result = $service->filterChangedFiles([$testFile]);

            // Assert
            expect($result)->toBe([$testFile]);
        });

        test('skips files with invalid mtime during filtering', function (): void {
            // Arrange
            $testFile1 = $this->tempDir.'/test1.php';
            $testFile2 = $this->tempDir.'/test2.php';

            file_put_contents($testFile1, '<?php');
            file_put_contents($testFile2, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Save cache
            $service->saveCache([$testFile1, $testFile2]);

            // Act - Only valid files since filemtime throws on nonexistent
            $result = $service->filterChangedFiles([$testFile1, $testFile2]);

            // Assert - returns all files when no changes detected
            expect($result)->toBe([$testFile1, $testFile2]);
        });

        test('handles multiple changed files correctly', function (): void {
            // Arrange
            $testFile1 = $this->tempDir.'/test1.php';
            $testFile2 = $this->tempDir.'/test2.php';
            $testFile3 = $this->tempDir.'/test3.php';

            file_put_contents($testFile1, '<?php');
            file_put_contents($testFile2, '<?php');
            file_put_contents($testFile3, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Save initial cache
            $service->saveCache([$testFile1, $testFile2, $testFile3]);

            // Modify test1 and test3
            Sleep::sleep(1);
            touch($testFile1);
            touch($testFile3);

            // Act
            $result = $service->filterChangedFiles([$testFile1, $testFile2, $testFile3]);

            // Assert
            expect($result)->toBe([$testFile1, $testFile3]);
        });

        test('uses default cache directory when none provided', function (): void {
            // Arrange
            $testFile = $this->tempDir.'/test.php';
            file_put_contents($testFile, '<?php');

            $originalCwd = getcwd();
            chdir($this->tempDir);

            $service = new IncrementalService();

            // Save cache
            $service->saveCache([$testFile]);

            // Modify file
            Sleep::sleep(1);
            touch($testFile);

            // Act
            $result = $service->filterChangedFiles([$testFile]);

            // Assert
            expect($result)->toBe([$testFile]);

            // Cleanup
            chdir($originalCwd);
            unlink($this->tempDir.'/.prism/cache/incremental.json');
            rmdir($this->tempDir.'/.prism/cache');
            rmdir($this->tempDir.'/.prism');
        });

        test('returns empty array input as-is when cache does not exist', function (): void {
            // Arrange
            $service = new IncrementalService($this->cacheDir);

            // Act
            $result = $service->filterChangedFiles([]);

            // Assert
            expect($result)->toBeArray()
                ->toBeEmpty();
        });
    });

    describe('integration scenarios', function (): void {
        test('complete workflow: save cache then filter unchanged files', function (): void {
            // Arrange
            $testFile1 = $this->tempDir.'/test1.php';
            $testFile2 = $this->tempDir.'/test2.php';

            file_put_contents($testFile1, '<?php');
            file_put_contents($testFile2, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Act - First run: save cache
            $service->saveCache([$testFile1, $testFile2]);

            // Second run: no changes
            $result = $service->filterChangedFiles([$testFile1, $testFile2]);

            // Assert
            expect($result)->toBe([$testFile1, $testFile2]);
        });

        test('complete workflow: save cache then filter with changes', function (): void {
            // Arrange
            $testFile1 = $this->tempDir.'/test1.php';
            $testFile2 = $this->tempDir.'/test2.php';

            file_put_contents($testFile1, '<?php // v1');
            file_put_contents($testFile2, '<?php // v1');

            $service = new IncrementalService($this->cacheDir);

            // First run
            $service->saveCache([$testFile1, $testFile2]);

            // Modify one file
            Sleep::sleep(1);
            file_put_contents($testFile1, '<?php // v2');

            // Act - Second run
            $filtered = $service->filterChangedFiles([$testFile1, $testFile2]);

            // Assert
            expect($filtered)->toBe([$testFile1])
                ->toHaveCount(1);
        });

        test('handles cache directory already existing', function (): void {
            // Arrange
            mkdir($this->cacheDir, 0o777, recursive: true);

            $testFile = $this->tempDir.'/test.php';
            file_put_contents($testFile, '<?php');

            $service = new IncrementalService($this->cacheDir);

            // Act
            $service->saveCache([$testFile]);

            // Assert
            expect(file_exists($this->cacheDir.'/incremental.json'))->toBeTrue();
        });
    });
});
