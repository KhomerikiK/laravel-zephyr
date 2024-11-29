<?php

namespace RedberryProducts\Zephyr\Commands;

use Illuminate\Console\Command;
use RedberryProducts\Zephyr\Helpers\RegexHelper;
use RedberryProducts\Zephyr\Traits\CommandsServicesTrait;

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
    protected $description = 'Run all automated tests in current test cycle';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): int
    {
        $projectKey = $this->argument('projectKey');
        $testCycleId = $this->argument('testCycleId');

        $testExecutions = $this->apiService->getTestExecutions($projectKey, $testCycleId);

        // Zephyr scale does not support directly retrieving test cases from cycles
        // We will first get text execution, get all cases inside it and then
        // check in all test cases if this case should be automated or not.
        $uniqueTestCaseIdsFromExecution = collect($testExecutions['values'])
            ->map(function ($value) {
                // Extract the portion after "testcases/" and before "/versions"
                return (string) str($value['testCase']['self'])
                    ->after('testcases/')
                    ->before('/versions');
            })
            ->unique()
            ->values() // Reset keys
            ->toArray();

        // Gets all available test cases from zephyr
        $testCases = $this->apiService->getTestCases($projectKey);

        // This filtering gets test cases that where matched in test execution and also
        // has attribute "Automated", which indicates that this test case
        // should be executed on automated testing
        $testCasesThatShouldBeAutomated = collect($testCases['values'])
            ->filter(function ($testCase) use ($uniqueTestCaseIdsFromExecution) {
                // Check if the test case key exists in the first filtered array
                return in_array($testCase['key'], $uniqueTestCaseIdsFromExecution)
                    && $testCase['customFields']['TestType'] === 'Automated'; // Ensure TestType is "Automation"
            })
            ->pluck('key') // Extract only the IDs of matching test cases
            ->values() // Reset array keys
            ->toArray();

        $testsMatcherRegexString = RegexHelper::buildTestFilesExecutionFilterRegex($testCasesThatShouldBeAutomated);

        $pestCommand = 'pest --filter=' . escapeshellarg($testsMatcherRegexString);

        $output = [];
        $result_code = 0;
        exec($pestCommand, $output, $result_code);

        $executionResult = implode("\n", $output);

        return self::SUCCESS;
    }
}
