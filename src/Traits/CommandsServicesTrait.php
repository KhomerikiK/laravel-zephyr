<?php

namespace RedberryProducts\Zephyr\Traits;

use RedberryProducts\Zephyr\Services\ApiService;
use RedberryProducts\Zephyr\Services\TestFilesManagerService;

trait CommandsServicesTrait
{
    protected ApiService $apiService;

    protected TestFilesManagerService $testFilesManager;

    public function __construct(ApiService $apiService, TestFilesManagerService $testFilesManager)
    {
        parent::__construct();
        $this->apiService = $apiService;
        $this->testFilesManager = $testFilesManager;
    }
}
