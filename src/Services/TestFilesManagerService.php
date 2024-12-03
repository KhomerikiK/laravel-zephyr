<?php

namespace RedberryProducts\Zephyr\Services;

use DOMDocument;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SimpleXMLElement;
use Str;

class TestFilesManagerService
{
    private array $existingTestIds;

    private string $projectKey;

    private mixed $commandInstance;

    public function setProjectKey(string $projectKey): TestFilesManagerService
    {
        $this->projectKey = $projectKey;

        return $this;
    }

    public function setCommandInstance(mixed $commandInstance): TestFilesManagerService
    {
        $this->commandInstance = $commandInstance;

        return $this;
    }

    public function setExistingTestIds(array $existingTestIds): TestFilesManagerService
    {
        $this->existingTestIds = $existingTestIds;

        return $this;
    }

    public function createFiles(array $structure, string $path): void
    {
        $this->createTestCases($structure, $path);
        $this->createChildren($structure, $path);
    }

    private function createTestCases(array $structure, string $path): void
    {
        if (isset($structure['test_cases'])) {
            foreach ($structure['test_cases'] as $testCase) {
                if ($testCase['customFields']['TestType'] !== 'Automated') {
                    continue;
                }

                if ($this->testCaseExists($testCase)) {
                    continue;
                }
                $testFilePath = $this->getTestFilePath($structure, $path, $testCase);
                $this->writeTestCaseToFile($testFilePath, $testCase);
            }
        }
    }

    private function createChildren(array $structure, string $path): void
    {
        if (isset($structure['children'])) {
            foreach ($structure['children'] as $node) {
                $newPath = $this->getNewPath($path, $node);
                Storage::disk('local')->makeDirectory($newPath);
                $this->createFiles($node, $newPath);
            }
        }
    }

    private function getTestFilePath(array $structure, string $path, array $testCase): string
    {
        $testCaseFileName = isset($structure['name']) ? (Str::slug(strtolower($structure['name'])) . '.php') : 'TestCases.php';

        return rtrim($path, '/') . '/' . $testCaseFileName;
    }

    /*
     * Check if test case exists in existing cases array
     */
    private function testCaseExists(array $testCase): bool
    {
        foreach ($this->existingTestIds as $testArray) {
            if ($testArray['fullTestCaseId'] === $testCase['key']) {
                $this->commandInstance->warn("Test case {$testCase['key']} already exists in {$testArray['filePathRelativeToBasePath']}, skipping");

                return true;
            }
        }

        return false;
    }

    private function writeTestCaseToFile(string $testFilePath, array $testCase): void
    {
        if (! Storage::disk('local')->fileExists($testFilePath)) {
            Storage::disk('local')->put($testFilePath, "<?php\n");
        }

        Storage::disk('local')->append($testFilePath, "\ntest('[{$testCase['key']}] {$testCase['name']}', function () {\n\n});\n");
    }

    private function getNewPath(string $path, array $node): string
    {
        return isset($node['name']) ? $path . '/' . Str::slug($node['name']) : $path;
    }

    /*
  *  Scans existing tests, gets names of Zephyr and puts in array
  */
    public function scanDirectoryForTestIds($dir): array
    {
        $result = [];
        $seenTests = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            // Skip directories
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getPathname();
            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

            // Should be PHP file. Otherwise, test case file name is incorrect
            if (strtolower($fileExtension) !== 'php') {
                continue;
            }

            $fileName = pathinfo($filePath, PATHINFO_FILENAME);

            // Should match the format. Otherwise, test case file name is incorrect
            if (! str($fileName)->is($this->projectKey . '-T*-*')) {
                continue;
            }

            $testCaseId = str($fileName)
                ->after($this->projectKey . '-T')
                ->before('-')
                ->toString();

            // Should be integer. Otherwise, test case file name is incorrect
            if (! ctype_digit($testCaseId)) {
                continue;
            }

            // Get full test case id (including project key)
            $fullTestCaseId = str($fileName)
                ->before('-')
                ->append('-', str($fileName)->after('-')->before('-'))
                ->trim()
                ->toString();

            // check for duplicate test id's and skip
            if (isset($seenTests[$fullTestCaseId])) {
                //echo "Warning: Duplicate test ID '$testId' found in file $filePath\n";
                continue;
            }
            $seenTests[$fullTestCaseId] = true;
            $result[] = [
                'fullTestCaseId'             => $fullTestCaseId,
                'fullFilePath'               => $filePath,
                'filePathRelativeToBasePath' => str($filePath)->after(base_path() . '/'),
            ];
        }

        return $result;
    }

    /*
    * Extracts test cases from junit xml object
    */
    public function extractTestcases(SimpleXMLElement $element): array
    {
        $testResults = [];

        foreach ($element->children() as $child) {
            if ($child->getName() === 'testsuite') {
                $testSuite = [
                    'name'       => (string) $child['name'],
                    'file'       => (string) $child['file'],
                    'tests'      => (int) $child['tests'],
                    'assertions' => (int) $child['assertions'],
                    'errors'     => (int) $child['errors'],
                    'failures'   => (string) $child['failures'],
                    'time'       => (float) $child['time'],
                    'error'      => '',
                ];

                // Extract error message from Junit file
                if ($testSuite['errors'] > 0 || $testSuite['failures'] > 0) {
                    foreach ($child->children() as $testCase) {
                        if ($testCase->getName() === 'testcase') {
                            foreach ($testCase->children() as $error) {
                                if ($error->getName() === 'error') {
                                    $testSuite['error'] = (string) $error;
                                    break 2; // Exit both loops when the error is found
                                }
                            }
                        }
                    }
                }

                $testResults[] = $testSuite;
            }
        }

        return $testResults;
    }

    private function mergeCypressJunitFilesIntoOne($sourceDirectory, $targetFile): void
    {
        $xml = new SimpleXMLElement('<testsuites/>');

        // Get all XML files in the source directory
        $files = glob($sourceDirectory . '/*.xml');
        foreach ($files as $file) {
            $content = simplexml_load_file($file);

            foreach ($content->testsuite as $testsuite) {
                $newTestsuite = $xml->addChild('testsuite');

                if ($testsuite->testsuite) {
                    foreach ($testsuite->testsuite as $nestedTestsuite) {
                        $newNestedTestsuite = $newTestsuite->addChild('testsuite');

                        if ($nestedTestsuite->testcase) {
                            foreach ($nestedTestsuite->testcase as $testcase) {
                                $newNestedTestsuite->addChild('testcase');
                            }
                        }
                    }
                }
            }
        }

        // Save the merged content to a new file
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $dom->save($targetFile);
    }
}
