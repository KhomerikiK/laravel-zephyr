<?php

namespace RedberryProducts\Zephyr\Services;

use DOMDocument;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RedberryProducts\Zephyr\Traits\TestPatternMatcherTrait;
use SimpleXMLElement;
use Str;

class TestFilesManagerService
{
    use TestPatternMatcherTrait;

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
            if ($testArray['test_id'] === $testCase['key']) {
                $this->commandInstance->warn("Test case {$testCase['key']} already exists in {$testArray['file']}, skipping");

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
            if ($file->isDir()) {
                continue;
            }
            $filePath = $file->getPathname();
            $content = file_get_contents($filePath);
            preg_match_all($this->getTestIdPattern($this->projectKey), $content, $matches);

            foreach ($matches[1] as $match) {
                $testIds = explode(',', $match);

                foreach ($testIds as $testId) {
                    $testId = trim($testId);
                    // check for duplicate test id's and skip
                    if (isset($seenTests[$testId])) {
                        //echo "Warning: Duplicate test ID '$testId' found in file $filePath\n";
                        continue;
                    }
                    $seenTests[$testId] = true;
                    $result[] = ['test_id' => $testId, 'file' => $filePath];
                }
            }
        }

        return $result;
    }

    /*
    * Extracts test cases from junit xml object
    */
    public function extractTestcases(SimpleXMLElement $element): array
    {
        $testcases = [];

        foreach ($element->children() as $child) {
            if ($child->getName() === 'testcase') {
                $testcase = [
                    'name'       => (string) $child['name'],
                    'class'      => (string) $child['class'],
                    'classname'  => (string) $child['classname'],
                    'file'       => (string) $child['file'],
                    'assertions' => (int) $child['assertions'],
                    'time'       => (float) $child['time'],
                    'failure'    => (string) $child->failure,
                ];

                $testcases[] = $testcase;
            } else {
                $testcases = array_merge($testcases, $this->extractTestcases($child));
            }
        }

        return $testcases;
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
