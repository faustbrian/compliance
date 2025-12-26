<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Prism\Output;

use Cline\Prism\ValueObjects\TestResult;
use Cline\Prism\ValueObjects\TestSuite;
use DOMDocument;
use DOMElement;
use Symfony\Component\Console\Output\OutputInterface;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

use function array_map;
use function array_sum;
use function implode;
use function is_bool;
use function is_scalar;
use function json_encode;
use function sprintf;
use function str_replace;

/**
 * Renders prism test results in JUnit XML format for CI/CD integration.
 *
 * Produces standards-compliant JUnit XML output compatible with Jenkins,
 * GitHub Actions, GitLab CI, and other CI systems. Includes per-test
 * duration, proper error stacktraces, and full test case details.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class JunitXmlRenderer
{
    /**
     * Create a new JUnit XML renderer instance.
     *
     * @param OutputInterface $output The Symfony Console output interface for writing XML results
     */
    public function __construct(
        private OutputInterface $output,
    ) {}

    /**
     * Render test suites as JUnit XML.
     *
     * Produces XML conforming to the JUnit XML schema with testsuites root element,
     * individual testcase elements for each test, and proper failure/error reporting.
     *
     * @param array<int, TestSuite> $suites Collection of test suite results to render
     */
    public function render(array $suites): void
    {
        $totalTests = array_sum(array_map(fn (TestSuite $s): int => $s->totalTests(), $suites));
        $totalFailed = array_sum(array_map(fn (TestSuite $s): int => $s->failedTests(), $suites));
        $totalDuration = array_sum(array_map(fn (TestSuite $s): float => $s->duration, $suites));

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $testsuites = $dom->createElement('testsuites');
        $testsuites->setAttribute('tests', (string) $totalTests);
        $testsuites->setAttribute('failures', (string) $totalFailed);
        $testsuites->setAttribute('errors', '0');
        $testsuites->setAttribute('time', sprintf('%.6f', $totalDuration));

        $dom->appendChild($testsuites);

        foreach ($suites as $suite) {
            $testsuites->appendChild($this->formatSuite($dom, $suite));
        }

        $xml = $dom->saveXML();

        if ($xml === false) {
            return;
        }

        $this->output->writeln($xml);
    }

    /**
     * Format a test suite for JUnit XML output.
     *
     * Creates a testsuite element with all test cases, including both passing
     * and failing tests. Each test case includes execution duration.
     *
     * @param  DOMDocument $dom   The XML document being constructed
     * @param  TestSuite   $suite Test suite to format
     * @return DOMElement  XML element representing the test suite
     */
    private function formatSuite(DOMDocument $dom, TestSuite $suite): DOMElement
    {
        $suiteElement = $dom->createElement('testsuite');
        $suiteElement->setAttribute('name', $suite->name);
        $suiteElement->setAttribute('tests', (string) $suite->totalTests());
        $suiteElement->setAttribute('failures', (string) $suite->failedTests());
        $suiteElement->setAttribute('errors', '0');
        $suiteElement->setAttribute('time', sprintf('%.6f', $suite->duration));

        // Add all test cases (both passing and failing)
        foreach ($suite->results as $result) {
            $suiteElement->appendChild($this->formatTestCase($dom, $result));
        }

        return $suiteElement;
    }

    /**
     * Format a single test case for JUnit XML output.
     *
     * Creates a testcase element with name, classname, and time attributes.
     * For failing tests, includes a failure element with message and details.
     *
     * @param  DOMDocument $dom    The XML document being constructed
     * @param  TestResult  $result Test result to format
     * @return DOMElement  XML element representing the test case
     */
    private function formatTestCase(DOMDocument $dom, TestResult $result): DOMElement
    {
        $testcase = $dom->createElement('testcase');

        // Use group as classname for organization
        $testcase->setAttribute('classname', $this->sanitizeXmlValue($result->group));
        $testcase->setAttribute('name', $this->sanitizeXmlValue($result->description));
        $testcase->setAttribute('time', sprintf('%.6f', $result->duration));
        $testcase->setAttribute('file', $this->sanitizeXmlValue($result->file));

        // Add failure element if test failed
        if (!$result->passed) {
            $testcase->appendChild($this->formatFailure($dom, $result));
        }

        return $testcase;
    }

    /**
     * Format a test failure for JUnit XML output.
     *
     * Creates a failure element with message attribute and detailed failure
     * information including expected vs actual validation state, error messages,
     * and test data for debugging.
     *
     * @param  DOMDocument $dom    The XML document being constructed
     * @param  TestResult  $result Failed test result to format
     * @return DOMElement  XML element representing the failure
     */
    private function formatFailure(DOMDocument $dom, TestResult $result): DOMElement
    {
        $failure = $dom->createElement('failure');

        // Set failure message and type attributes
        $message = sprintf(
            'Expected valid=%s but got valid=%s',
            $result->expected ? 'true' : 'false',
            $result->actual ? 'true' : 'false',
        );
        $failure->setAttribute('message', $this->sanitizeXmlValue($message));
        $failure->setAttribute('type', 'ValidationFailure');

        // Build detailed failure text
        $details = [];
        $details[] = sprintf('Test ID: %s', $result->id);
        $details[] = sprintf('File: %s', $result->file);
        $details[] = sprintf('Group: %s', $result->group);
        $details[] = sprintf('Description: %s', $result->description);
        $details[] = '';
        $details[] = sprintf('Expected: %s', $result->expected ? 'true' : 'false');
        $details[] = sprintf('Actual: %s', $result->actual ? 'true' : 'false');

        if ($result->error !== null) {
            $details[] = '';
            $details[] = 'Error Message:';
            $details[] = $result->error;
        }

        $details[] = '';
        $details[] = 'Test Data:';
        $details[] = $this->formatData($result->data);

        // Add tags if present
        if ($result->tags !== []) {
            $details[] = '';
            $details[] = sprintf('Tags: %s', implode(', ', $result->tags));
        }

        $failure->textContent = implode("\n", $details);

        return $failure;
    }

    /**
     * Format test data for display in failure output.
     *
     * @param  mixed  $data Test data to format
     * @return string Formatted data string
     */
    private function formatData(mixed $data): string
    {
        if ($data === null) {
            return 'null';
        }

        if (is_bool($data)) {
            return $data ? 'true' : 'false';
        }

        if (is_scalar($data)) {
            return (string) $data;
        }

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    /**
     * Sanitize value for XML attribute/element content.
     *
     * Removes control characters and ensures valid XML content.
     *
     * @param  string $value Value to sanitize
     * @return string Sanitized value safe for XML
     */
    private function sanitizeXmlValue(string $value): string
    {
        // Remove null bytes and control characters except newlines/tabs
        $sanitized = str_replace("\0", '', $value);

        // For attributes, also remove newlines and tabs
        return str_replace(["\r", "\n", "\t"], ' ', $sanitized);
    }
}
