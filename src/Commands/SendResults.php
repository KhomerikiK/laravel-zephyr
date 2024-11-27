<?php

namespace RedberryProducts\Zephyr\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use RedberryProducts\Zephyr\Traits\CommandsServicesTrait;
use RedberryProducts\Zephyr\Traits\TestPatternMatcherTrait;

class SendResults extends Command
{
    use CommandsServicesTrait;
    use TestPatternMatcherTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'zephyr:results {projectKey}';

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

        $testResultsArray = [
            'version'    => 1,
            'executions' => [],
        ];

        $xml = Storage::disk('local')->get('junit.xml');
        $xmlObject = simplexml_load_string($xml);
        $projectKey = $this->argument('projectKey');

        $this->testFilesManager->setProjectKey($projectKey)
            ->setCommandInstance($this);

        $testcases = $this->testFilesManager->extractTestcases($xmlObject);
        foreach ($testcases as $testcase) {
            preg_match_all($this->getTestIdPattern($projectKey), $testcase['name'], $matches);

            foreach ($matches[1] as $match) {
                $testIds = explode(',', $match);

                foreach ($testIds as $testId) {
                    $testId = trim($testId);
                    $testResultsArray['executions'][] = [
                        'result'   => $testcase['failure'] ? 'Failed' : 'Passed',
                        'testCase' => [
                            'comment' => $testcase['failure'],
                            'key'     => $testId,
                        ],
                    ];
                }
            }

        }
        // TODO: maybe we need to log something here ($result->json())
        $result = $this->apiService->sendCustomTestResultsToZephyr($projectKey, $testResultsArray);

        return self::SUCCESS;
    }

}
