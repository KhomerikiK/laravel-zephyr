<?php

namespace RedberryProducts\Zephyr\Commands;

use Illuminate\Console\Command;
use RedberryProducts\Zephyr\Helpers\PatternsMatcherHelper;
use RedberryProducts\Zephyr\Traits\CommandsServicesTrait;
use Symfony\Component\Process\Process;

class RunTestCycle extends Command
{
    use CommandsServicesTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'zephyr:run-cycle {projectKey} {testCycleId}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'This command gets tests that should be executed for particular test cycle, executes it and saves Junit result of it.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): int
    {
        $projectKey = $this->argument('projectKey');
        $testCycleId = $this->argument('testCycleId');

        $this
            ->testFilesManager
            ->setProjectKey($projectKey)
            ->setCommandInstance($this);

        $this->info('Getting test execution details...');

        $testExecutions = $this
            ->apiService
            ->getTestExecutions($projectKey, $testCycleId);

        if (! $testExecutions) {
            $this->error('No tests found that should be executed. Please check test cycle / execution.');

            return self::FAILURE;
        }

        $uniqueTestCaseIdsFromExecution = $this
            ->getUniqueTestCaseIdsFromExecution($testExecutions);

        $this->info('Getting test cases...');
        // Gets all available test cases from zephyr
        $testCases = $this
            ->apiService
            ->getTestCases($projectKey);

        if (! $testCases) {
            $this->error('No test cases found. Please create them in order to proceed.');

            return self::FAILURE;
        }

        $testCasesThatArePartOfTestCycle = $this
            ->getTestCasesThatArePartOfTestCycle($testCases, $uniqueTestCaseIdsFromExecution);

        $this->info('Scanning directory for tests...');

        $scannedZephyrTestFiles = $this
            ->testFilesManager
            ->scanDirectoryForTestIds(base_path('tests/Browser'));

        $testCasesThatShouldBeExecuted = $this
            ->getTestCasesThatShouldBeExecuted($scannedZephyrTestFiles, $testCasesThatArePartOfTestCycle);

        if (! $testCasesThatShouldBeExecuted) {
            $this->warn('No test cases found that should be executed.');

            return self::SUCCESS;
        }

        $duskTestFilesCLICommand = PatternsMatcherHelper::buildTestFilesIdsParameterForCLI($testCasesThatShouldBeExecuted);

        $this->info('Executing tests...');
        $process = Process::fromShellCommandline(
            command: "php artisan dusk --log-junit storage/app/junit.xml $duskTestFilesCLICommand",
            timeout: null
        );

        $process->setTty(true); // Enable real-time terminal output if supported

        // Run command and output everything
        $process->run(function ($type, $buffer) {
            if ($type === Process::ERR) {
                echo "Error: $buffer";
            } else {
                echo $buffer;
            }
        });

        return self::SUCCESS;
    }

    private function getUniqueTestCaseIdsFromExecution(array $testExecutions): array
    {
        // Zephyr scale does not support directly retrieving test cases from cycles
        // We will first get text execution, get all cases inside it and then
        // check in all test cases if this case should be automated or not.

        return collect($testExecutions['values'])
            ->map(function ($value) {
                // Extract the portion after "testcases/" and before "/versions"
                return (string) str($value['testCase']['self'])
                    ->after('testcases/')
                    ->before('/versions');
            })
            ->unique()
            ->values() // Reset keys
            ->toArray();
    }

    private function getTestCasesThatArePartOfTestCycle(array $testCases, array $uniqueTestCaseIdsFromExecution): array
    {
        // This filtering gets test cases that where matched in test execution and also
        // has attribute "Automated", which indicates that this test case
        // should be executed on automated testing

        // This step is only needed to read TestType, as only test cases have custom field
        // TestType, not test executions.

        return collect($testCases['values'])
            ->filter(function ($testCase) use ($uniqueTestCaseIdsFromExecution) {
                // Check if the test case key exists in the first filtered array
                return in_array($testCase['key'], $uniqueTestCaseIdsFromExecution)
                    && $testCase['customFields']['TestType'] === 'Automated'; // Ensure TestType is "Automation"
            })
            ->pluck('key') // Extract only the IDs of matching test cases
            ->values() // Reset array keys
            ->toArray();
    }

    private function getTestCasesThatShouldBeExecuted(array $scannedZephyrTestFiles, array $testCasesThatArePartOfTestCycle): array
    {
        // Here, we got test case id's that are both in cycle and in repository. We got
        // path for them and they are not ready to be executed.

        return collect($scannedZephyrTestFiles)
            ->filter(function ($scannedTestCase) use ($testCasesThatArePartOfTestCycle) {
                return in_array(
                    $scannedTestCase['fullTestCaseId'],
                    $testCasesThatArePartOfTestCycle
                );
            })
            ->pluck('filePathRelativeToBasePath')
            ->map(function ($value) {
                // Convert stringable to string
                return (string) $value;
            })
            ->toArray();
    }
}
