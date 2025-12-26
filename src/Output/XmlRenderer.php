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

use const JSON_THROW_ON_ERROR;

use function array_map;
use function array_sum;
use function is_array;
use function is_bool;
use function is_scalar;
use function json_encode;
use function round;

/**
 * Renders prism test results in XML format.
 *
 * Produces structured XML output suitable for programmatic consumption,
 * CI/CD pipelines (e.g., JUnit format compatibility), and integration
 * with XML-based tools. Includes summary statistics and optionally
 * detailed failure information.
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class XmlRenderer
{
    /**
     * Create a new XML renderer instance.
     *
     * @param OutputInterface $output          The Symfony Console output interface for writing
     * @param bool            $includeFailures Whether to include detailed failure information in output
     */
    public function __construct(
        private OutputInterface $output,
        private bool $includeFailures = false,
    ) {}

    /**
     * Render test suites as XML.
     *
     * @param array<int, TestSuite> $suites Collection of test suite results to render
     */
    public function render(array $suites): void
    {
        $totalTests = array_sum(array_map(fn (TestSuite $s): int => $s->totalTests(), $suites));
        $totalPassed = array_sum(array_map(fn (TestSuite $s): int => $s->passedTests(), $suites));
        $totalFailed = array_sum(array_map(fn (TestSuite $s): int => $s->failedTests(), $suites));
        $totalDuration = array_sum(array_map(fn (TestSuite $s): float => $s->duration, $suites));

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('prism_results');
        $dom->appendChild($root);

        // Add summary
        $summary = $dom->createElement('summary');
        $summary->appendChild($this->createElement($dom, 'total', (string) $totalTests));
        $summary->appendChild($this->createElement($dom, 'passed', (string) $totalPassed));
        $summary->appendChild($this->createElement($dom, 'failed', (string) $totalFailed));

        $passRate = $totalTests > 0 ? round(($totalPassed / $totalTests) * 100, 1) : 0.0;
        $summary->appendChild($this->createElement($dom, 'pass_rate', (string) $passRate));
        $summary->appendChild($this->createElement($dom, 'duration', (string) round($totalDuration, 2)));

        $root->appendChild($summary);

        // Add suites
        $suitesElement = $dom->createElement('suites');

        foreach ($suites as $suite) {
            $suitesElement->appendChild($this->formatSuite($dom, $suite));
        }

        $root->appendChild($suitesElement);

        $xml = $dom->saveXML();

        if ($xml === false) {
            return;
        }

        $this->output->writeln($xml);
    }

    /**
     * Format a test suite for XML output.
     *
     * @param  DOMDocument $dom   The XML document being constructed
     * @param  TestSuite   $suite Test suite to format
     * @return DOMElement  XML element representing the test suite
     */
    private function formatSuite(DOMDocument $dom, TestSuite $suite): DOMElement
    {
        $suiteElement = $dom->createElement('suite');
        $suiteElement->appendChild($this->createElement($dom, 'name', $suite->name));
        $suiteElement->appendChild($this->createElement($dom, 'total', (string) $suite->totalTests()));
        $suiteElement->appendChild($this->createElement($dom, 'passed', (string) $suite->passedTests()));
        $suiteElement->appendChild($this->createElement($dom, 'failed', (string) $suite->failedTests()));
        $suiteElement->appendChild($this->createElement($dom, 'pass_rate', (string) round($suite->passRate(), 1)));
        $suiteElement->appendChild($this->createElement($dom, 'duration', (string) round($suite->duration, 2)));

        if ($this->includeFailures && $suite->failedTests() > 0) {
            $failuresElement = $dom->createElement('failures');

            foreach ($suite->failures() as $result) {
                $failuresElement->appendChild($this->formatFailure($dom, $result));
            }

            $suiteElement->appendChild($failuresElement);
        }

        return $suiteElement;
    }

    /**
     * Format a test failure for XML output.
     *
     * @param  DOMDocument $dom    The XML document being constructed
     * @param  TestResult  $result Test result to format
     * @return DOMElement  XML element representing the test failure
     */
    private function formatFailure(DOMDocument $dom, TestResult $result): DOMElement
    {
        $failure = $dom->createElement('failure');
        $failure->appendChild($this->createElement($dom, 'id', $result->id));
        $failure->appendChild($this->createElement($dom, 'file', $result->file));
        $failure->appendChild($this->createElement($dom, 'group', $result->group));
        $failure->appendChild($this->createElement($dom, 'description', $result->description));
        $failure->appendChild($this->createElement($dom, 'expected', $result->expected ? 'true' : 'false'));
        $failure->appendChild($this->createElement($dom, 'actual', $result->actual ? 'true' : 'false'));

        if ($result->error !== null) {
            $failure->appendChild($dom->createElement('error', $result->error));
        }

        $failure->appendChild($this->createDataElement($dom, $result->data));

        return $failure;
    }

    /**
     * Create a simple text element.
     *
     * @param  DOMDocument $dom   The XML document being constructed
     * @param  string      $name  Element name
     * @param  string      $value Element text content
     * @return DOMElement  Created XML element
     */
    private function createElement(DOMDocument $dom, string $name, string $value): DOMElement
    {
        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode($value));

        return $element;
    }

    /**
     * Create a data element with appropriate formatting for complex types.
     *
     * @param  DOMDocument $dom  The XML document being constructed
     * @param  mixed       $data Data to format
     * @return DOMElement  XML element containing the formatted data
     */
    private function createDataElement(DOMDocument $dom, mixed $data): DOMElement
    {
        $element = $dom->createElement('data');

        if (null === $data) {
            $element->setAttribute('type', 'null');
        } elseif (is_bool($data)) {
            $element->setAttribute('type', 'boolean');
            $element->textContent = $data ? 'true' : 'false';
        } elseif (is_scalar($data)) {
            $element->setAttribute('type', 'scalar');
            $element->textContent = (string) $data;
        } elseif (is_array($data)) {
            $element->setAttribute('type', 'json');
            $element->textContent = json_encode($data, JSON_THROW_ON_ERROR);
        } else {
            $element->setAttribute('type', 'unknown');
            $element->textContent = json_encode($data, JSON_THROW_ON_ERROR);
        }

        return $element;
    }
}
