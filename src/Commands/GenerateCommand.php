<?php

namespace RedberryProducts\Zephyr\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RedberryProducts\Zephyr\Helpers\TestsStructureArrayBuilder;
use RedberryProducts\Zephyr\Traits\CommandsServicesTrait;

class GenerateCommand extends Command
{
    use CommandsServicesTrait;

    protected $signature = 'zephyr:generate {projectKey}';

    protected $description = 'Generate test files for Laravel';

    protected array $testCases;

    protected array $folders;

    private function getTestCasesDataFromZephyr(): void
    {
        $testCases = [];
        $folders = [];
        $testModeEnabled = config('zephyr.test_mode');

        if ($testModeEnabled) {
            if (Storage::disk('local')->fileExists('testCases.json') && Storage::disk('local')->fileExists('folders.json')) {
                $testCases = json_decode(Storage::disk('local')->get('testCases.json'), true);
                $folders = json_decode(Storage::disk('local')->get('folders.json'), true);
            }
        }

        if (empty($testCases) || empty($folders)) {
            $testCases = $this->apiService->getTestCases($this->argument('projectKey'));
            $folders = $this->apiService->getFolders($this->argument('projectKey'));
            if ($testModeEnabled) {
                $this->saveJsonDataAsFiles($testCases, $folders);
            }
        }

        $this->testCases = $testCases;
        $this->folders = $folders;
    }

    public function handle(): int
    {
        $this->getTestCasesDataFromZephyr();
        $this->createTestsDirectoryIfNotExists();

        // Create a structure of folders / files that should be created
        $testsStructureArray = (new TestsStructureArrayBuilder($this->folders['values'], $this->testCases['values']))->build();

        // Create test folders / files
        $this->testFilesManager->setProjectKey($this->argument('projectKey'))
            ->setCommandInstance($this);

        // Retrieve existing tests
        $existingTestIds = $this->testFilesManager
            ->scanDirectoryForTestIds(base_path('tests/Browser'));

        // Create test folders / files
        $this->testFilesManager->setExistingTestIds($existingTestIds)
            ->createFiles($testsStructureArray, 'tests/Browser');

        $this->cleanupEmptyFolders(Storage::disk('local')->path('tests/Browser'));

        return self::SUCCESS;

    }

    /*
     * Triggered only if test mode is enabled. Saves data to json files to make development process easier
     */
    private function saveJsonDataAsFiles($testCases, $folders): void
    {
        Storage::disk('local')->put('testCases.json', json_encode($testCases));
        Storage::disk('local')->put('folders.json', json_encode($folders));
    }

    /*
     *
     */
    private function createTestsDirectoryIfNotExists(): void
    {
        if (! Storage::disk('local')->directoryExists('tests/Browser')) {
            Storage::disk('local')->makeDirectory('tests/Browser');
        }
    }

    /*
     * todo@kosta Maybe make this more readable? :)
     */
    public function cleanupEmptyFolders($path): void
    {
        if (! is_dir($path)) {
            $this->error('Invalid dir');

            return;
        }

        // Scan the directory
        $files = scandir($path);

        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $filePath = $path . '/' . $file;

            if (is_dir($filePath)) {
                $this->cleanupEmptyFolders($filePath);
            }
        }

        // Re-scan after recursion
        $files = scandir($path);

        // Filter out system pointers
        $files = array_filter($files, function ($file) {
            return ! in_array($file, ['.', '..']);
        });

        // If empty, delete the directory
        if (count($files) == 0) {
            rmdir($path);
        }
    }
}
