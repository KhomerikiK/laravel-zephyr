<?php

namespace RedberryProducts\Zephyr\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RedberryProducts\Zephyr\Traits\CommandsServicesTrait;

class SendResults extends Command
{
    use CommandsServicesTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'zephyr:results {projectKey} {testCycleId}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Send test results to zephyr server';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): int
    {

        $projectKey = $this->argument('projectKey');
        $testCycleId = $this->argument('testCycleId');

        $xml = Storage::disk('local')->get('junit.xml');
        $xmlObject = simplexml_load_string($xml);

        $this->testFilesManager->setProjectKey($projectKey)
            ->setCommandInstance($this);

        $testExecutions = $this
            ->apiService
            ->getTestExecutions($projectKey, $testCycleId);

        $filteredTestExecutions = $this->filterLatestExecutionsForTestCases($testExecutions);

        $rawJunitTestsResults = $this->testFilesManager->extractTestcases($xmlObject);

        $finalResults = $this->matchExecutionsForTestCases($filteredTestExecutions, $rawJunitTestsResults);

        foreach ($finalResults as $testResult) {
            $this->apiService->updateTestExecution(
                // TODO: Move status names in .env?
                statusName: (int) $testResult['errors'] > 0 ? 'Fail' : 'Pass',
                testExecutionId: $testResult['testExecutionId'],
                executionTime: $testResult['time'],
                comment: $testResult['error']
            );
        }

        return self::SUCCESS;
    }

    private function filterLatestExecutionsForTestCases(array $executions): array
    {
        // Executions might be multiple for one test case. We will get latest execution from
        // executions array and use it to update executions on Zephyr
        return collect($executions['values'])
            ->groupBy(fn ($execution) => str($execution['testCase']['self'])->after('testcases/')->before('/versions')->toString())
            ->map(function ($group) {
                return $group->sortByDesc(function ($execution) {
                    // Extract only the numeric part of the key using regex
                    $executionNumberString = preg_replace('/[^0-9]/', '', $execution['key']);

                    return (int) $executionNumberString;
                })->first();
            })
            ->map(fn ($execution) => $execution['key'])
            ->toArray();
    }

    private function matchExecutionsForTestCases(array $filteredTestExecutions, array $rawJunitTestsResults): array
    {

        $finalTestCasesArray = [];

        foreach ($filteredTestExecutions as $testCase => $testExecution) {
            $testCaseThatShouldBeMatched = str($testCase)->replace('-', '')->toString();

            foreach ($rawJunitTestsResults as $rawJunitTestsResult) {
                $extractedFileNameThatShouldBeMatched = str($rawJunitTestsResult['name'])->afterLast('\\');
                if (str($extractedFileNameThatShouldBeMatched)->startsWith($testCaseThatShouldBeMatched)) {
                    $updatedJunitTestResult = [
                        'testExecutionId' => $testExecution,
                        'testCaseId'      => $testCase,
                        ...$rawJunitTestsResult,
                    ];
                    $finalTestCasesArray[] = $updatedJunitTestResult;
                    break;
                }

            }

        }

        return $finalTestCasesArray;
    }
}
