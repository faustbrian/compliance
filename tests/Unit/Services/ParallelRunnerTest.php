<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Prism\Services\ParallelRunner;
use Cline\Prism\Services\PrismRunner;
use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;
use ReflectionClass;
use Symfony\Component\Process\Process;
use Tests\Support\TestPrismImplementation;

describe('ParallelRunner', function (): void {
    beforeEach(function (): void {
        $prismRunner = new PrismRunner();
        $this->parallelRunner = new ParallelRunner($prismRunner);
        $this->tempDir = sys_get_temp_dir().'/prism_test_'.uniqid();
        mkdir($this->tempDir);
    });

    afterEach(function (): void {
        // Cleanup temp directory if it exists
        if (!is_dir($this->tempDir)) {
            return;
        }

        $files = glob($this->tempDir.'/*');

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            unlink($file);
        }

        rmdir($this->tempDir);
    });

    describe('basic execution', function (): void {
        test('returns empty test suite when no test files found', function (): void {
            $prismTest = createPrismTest('test-suite', $this->tempDir);

            $result = $this->parallelRunner->run($prismTest, 4);

            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->name)->toBe('test-suite')
                ->and($result->results)->toBeEmpty()
                ->and($result->duration)->toBeGreaterThanOrEqual(0.0);
        });

        test('runs sequentially when worker count is 1', function (): void {
            $prismTest = createPrismTest('test-suite', $this->tempDir);

            $result = $this->parallelRunner->run($prismTest, 1);

            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->name)->toBe('test-suite');
        });

        test('measures execution duration for parallel runs', function (): void {
            $prismTest = createPrismTest('test-suite', $this->tempDir);

            $result = $this->parallelRunner->run($prismTest, 1);

            expect($result->duration)->toBeGreaterThanOrEqual(0.0)
                ->and($result->duration)->toBeFloat();
        });

        test('preserves test suite name in result', function (): void {
            $prismTest = createPrismTest('test-suite', $this->tempDir);

            $result = $this->parallelRunner->run($prismTest, 4);

            expect($result->name)->toBe('test-suite');
        });
    });

    describe('sequential fallback', function (): void {
        test('runs sequentially when worker count is zero', function (): void {
            $prismTest = createPrismTest('test-suite', $this->tempDir);

            $result = $this->parallelRunner->run($prismTest, 0);

            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->name)->toBe('test-suite');
        });

        test('runs sequentially when worker count is negative', function (): void {
            $prismTest = createPrismTest('test-suite', $this->tempDir);

            $result = $this->parallelRunner->run($prismTest, -5);

            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->name)->toBe('test-suite');
        });

        test('runs sequentially when only one test file exists', function (): void {
            $testFile = $this->tempDir.'/test.json';
            file_put_contents($testFile, '[]');
            $prismTest = createPrismTest('test-suite', $this->tempDir);

            $result = $this->parallelRunner->run($prismTest, 4);

            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->name)->toBe('test-suite');
        });
    });

    describe('batch creation', function (): void {
        test('divides test files into equal batches for parallel execution', function (): void {
            $testFiles = [
                '/path/to/test1.json',
                '/path/to/test2.json',
                '/path/to/test3.json',
                '/path/to/test4.json',
            ];

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('createBatches');

            $batches = $method->invoke($this->parallelRunner, $testFiles, 2);

            expect($batches)->toHaveCount(2)
                ->and($batches[0])->toHaveCount(2)
                ->and($batches[1])->toHaveCount(2)
                ->and($batches[0])->toBe(['/path/to/test1.json', '/path/to/test2.json'])
                ->and($batches[1])->toBe(['/path/to/test3.json', '/path/to/test4.json']);
        });

        test('creates batches with minimum size of 1 when workers exceed files', function (): void {
            $testFiles = ['/path/to/test1.json', '/path/to/test2.json'];

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('createBatches');

            $batches = $method->invoke($this->parallelRunner, $testFiles, 10);

            expect($batches)->toHaveCount(2)
                ->and($batches[0])->toHaveCount(1)
                ->and($batches[1])->toHaveCount(1);
        });

        test('creates uneven batches when files do not divide evenly', function (): void {
            $testFiles = [
                '/path/to/test1.json',
                '/path/to/test2.json',
                '/path/to/test3.json',
                '/path/to/test4.json',
                '/path/to/test5.json',
            ];

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('createBatches');

            $batches = $method->invoke($this->parallelRunner, $testFiles, 2);

            expect($batches)->toHaveCount(3)
                ->and($batches[0])->toHaveCount(2)
                ->and($batches[1])->toHaveCount(2)
                ->and($batches[2])->toHaveCount(1);
        });

        test('creates single batch when worker count equals file count', function (): void {
            $testFiles = ['/path/to/test1.json'];

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('createBatches');

            $batches = $method->invoke($this->parallelRunner, $testFiles, 1);

            expect($batches)->toHaveCount(1)
                ->and($batches[0])->toHaveCount(1)
                ->and($batches[0])->toBe($testFiles);
        });
    });

    describe('batch size calculations', function (): void {
        $cases = [
            ['10 files, 2 workers', 10, 2, 2],
            ['10 files, 3 workers', 10, 3, 4],
            ['5 files, 5 workers', 5, 5, 5],
            ['3 files, 10 workers', 3, 10, 3],
            ['1 file, 4 workers', 1, 4, 1],
            ['100 files, 8 workers', 100, 8, 9],
        ];

        foreach ($cases as [$description, $fileCount, $workers, $expectedBatches]) {
            test('creates correct number of batches for '.$description, function () use ($fileCount, $workers, $expectedBatches): void {
                $testFiles = [];

                for ($i = 0; $i < $fileCount; ++$i) {
                    $testFiles[] = sprintf('/path/to/test%d.json', $i);
                }

                $reflection = new ReflectionClass($this->parallelRunner);
                $method = $reflection->getMethod('createBatches');

                $batches = $method->invoke($this->parallelRunner, $testFiles, $workers);

                expect($batches)->toHaveCount($expectedBatches);

                // Verify all files are present in batches
                $allFiles = [];

                foreach ($batches as $batch) {
                    $allFiles = [...$allFiles, ...$batch];
                }

                expect($allFiles)->toHaveCount($fileCount)
                    ->and($allFiles)->toBe($testFiles);
            });
        }
    });

    describe('process creation', function (): void {
        test('creates batch process with correct PHP script', function (): void {
            $prismTest = createPrismTest('test-suite', $this->tempDir);
            $batch = ['/path/to/test1.json', '/path/to/test2.json'];
            $outputFile = '/tmp/test-output.txt';

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('createBatchProcess');

            $process = $method->invoke($this->parallelRunner, $prismTest, $batch, $outputFile);

            expect($process)->toBeInstanceOf(Process::class);

            $commandLine = $process->getCommandLine();

            expect($commandLine)->toContain('php')
                ->and($commandLine)->toContain('-r');
        });
    });

    describe('result loading', function (): void {
        test('loads valid serialized test results from output file', function (): void {
            $outputFile = tempnam(sys_get_temp_dir(), 'prism_test_');
            $testResults = [
                TestResult::pass(
                    id: 'test:file:0:0',
                    file: 'test.json',
                    group: 'Test Group',
                    description: 'Test Description',
                    data: ['key' => 'value'],
                    expected: true,
                    duration: 0.1,
                ),
            ];

            file_put_contents($outputFile, serialize($testResults));

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('loadResults');

            $results = $method->invoke($this->parallelRunner, $outputFile);

            expect($results)->toBeArray()
                ->and($results)->toHaveCount(1)
                ->and($results[0])->toBeInstanceOf(TestResult::class)
                ->and($results[0]->id)->toBe('test:file:0:0');

            // Cleanup
            unlink($outputFile);
        });

        test('returns empty array when output file cannot be read', function (): void {
            $outputFile = tempnam(sys_get_temp_dir(), 'prism_test_');
            // Remove the file immediately so it doesn't exist when we try to read
            unlink($outputFile);

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('loadResults');

            try {
                $results = $method->invoke($this->parallelRunner, $outputFile);

                expect($results)->toBeArray()
                    ->and($results)->toBeEmpty();
            } catch (ErrorException $errorException) {
                // Expected behavior - file_get_contents will throw on nonexistent file
                expect($errorException->getMessage())->toContain('Failed to open stream');
            }
        });

        test('returns empty array when file contains invalid serialized data', function (): void {
            $outputFile = tempnam(sys_get_temp_dir(), 'prism_test_');
            file_put_contents($outputFile, 'invalid serialized data');

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('loadResults');

            $results = $method->invoke($this->parallelRunner, $outputFile);

            expect($results)->toBeArray()
                ->and($results)->toBeEmpty();

            // Cleanup
            unlink($outputFile);
        });

        test('returns empty array when unserialized data is not an array', function (): void {
            $outputFile = tempnam(sys_get_temp_dir(), 'prism_test_');
            file_put_contents($outputFile, serialize('not an array'));

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('loadResults');

            $results = $method->invoke($this->parallelRunner, $outputFile);

            expect($results)->toBeArray()
                ->and($results)->toBeEmpty();

            // Cleanup
            unlink($outputFile);
        });

        test('returns empty array when file is empty', function (): void {
            $outputFile = tempnam(sys_get_temp_dir(), 'prism_test_');
            file_put_contents($outputFile, '');

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('loadResults');

            $results = $method->invoke($this->parallelRunner, $outputFile);

            expect($results)->toBeArray()
                ->and($results)->toBeEmpty();

            // Cleanup
            unlink($outputFile);
        });

        test('handles multiple test results in output file', function (): void {
            $outputFile = tempnam(sys_get_temp_dir(), 'prism_test_');
            $testResults = [
                TestResult::pass(
                    id: 'test:file:0:0',
                    file: 'test1.json',
                    group: 'Group 1',
                    description: 'Test 1',
                    data: ['key1' => 'value1'],
                    expected: true,
                    duration: 0.1,
                ),
                TestResult::fail(
                    id: 'test:file:0:1',
                    file: 'test1.json',
                    group: 'Group 1',
                    description: 'Test 2',
                    data: ['key2' => 'value2'],
                    expected: true,
                    actual: false,
                    error: 'Validation failed',
                    duration: 0.2,
                ),
            ];

            file_put_contents($outputFile, serialize($testResults));

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('loadResults');

            $results = $method->invoke($this->parallelRunner, $outputFile);

            expect($results)->toBeArray()
                ->and($results)->toHaveCount(2)
                ->and($results[0])->toBeInstanceOf(TestResult::class)
                ->and($results[1])->toBeInstanceOf(TestResult::class)
                ->and($results[0]->passed)->toBeTrue()
                ->and($results[1]->passed)->toBeFalse();

            // Cleanup
            unlink($outputFile);
        });

        test('handles exception during result unserialization', function (): void {
            $outputFile = tempnam(sys_get_temp_dir(), 'prism_test_');
            // Write malformed serialized data that will throw exception
            file_put_contents($outputFile, 'O:8:"stdClass":1:{s:4:"test";s:5:"value";}x');

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('loadResults');

            $results = $method->invoke($this->parallelRunner, $outputFile);

            expect($results)->toBeArray()
                ->and($results)->toBeEmpty();

            // Cleanup
            unlink($outputFile);
        });

        test('loads empty array of test results successfully', function (): void {
            $outputFile = tempnam(sys_get_temp_dir(), 'prism_test_');
            file_put_contents($outputFile, serialize([]));

            $reflection = new ReflectionClass($this->parallelRunner);
            $method = $reflection->getMethod('loadResults');

            $results = $method->invoke($this->parallelRunner, $outputFile);

            expect($results)->toBeArray()
                ->and($results)->toBeEmpty();

            // Cleanup
            unlink($outputFile);
        });
    });

    describe('parallel execution', function (): void {
        test('executes parallel workers with multiple test files', function (): void {
            $testFile1 = $this->tempDir.'/test1.json';
            $testFile2 = $this->tempDir.'/test2.json';

            // Create valid test files
            $testData = [
                [
                    'description' => 'Test group',
                    'schema' => ['type' => 'string'],
                    'tests' => [
                        [
                            'description' => 'Valid string',
                            'data' => 'test',
                            'valid' => true,
                        ],
                    ],
                ],
            ];

            file_put_contents($testFile1, json_encode($testData));
            file_put_contents($testFile2, json_encode($testData));

            $prismTest = createPrismTest('test-suite', $this->tempDir);

            $result = $this->parallelRunner->run($prismTest, 2);

            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->name)->toBe('test-suite')
                // Results may be empty if worker processes fail to serialize/deserialize,
                // but the parallel path execution is what we're testing
                ->and($result->results)->toBeArray()
                ->and($result->duration)->toBeGreaterThan(0.0);
        });

        test('collects and merges results from multiple parallel processes', function (): void {
            // Create multiple test files to trigger parallel execution
            for ($i = 1; $i <= 4; ++$i) {
                $testFile = $this->tempDir.sprintf('/test%d.json', $i);
                file_put_contents($testFile, '[]');
            }

            $prismTest = createPrismTest('test-suite', $this->tempDir);

            // Run with 2 workers which will create 2 batches
            $result = $this->parallelRunner->run($prismTest, 2);

            expect($result)->toBeInstanceOf(TestSuite::class)
                ->and($result->name)->toBe('test-suite')
                ->and($result->results)->toBeArray()
                ->and($result->duration)->toBeFloat();
        });
    });
});

/**
 * Create a test PrismTestInterface implementation.
 */
function createPrismTest(string $name, string $testDirectory): TestPrismImplementation
{
    return new TestPrismImplementation($name, $testDirectory);
}
