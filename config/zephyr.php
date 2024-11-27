<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Project key for Zephyr
    |--------------------------------------------------------------------------
    |
    | Zephyr needs project key to know which project it works with. Usually it's
    | something like `AR`, `COL` etc. You can find your project key in Jira
    |
    */

    'project_key' => env('JIRA_PROJECT_KEY'),

    'api_key' => env('ZEPHYR_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Test Mode
    |--------------------------------------------------------------------------
    |
    | Test mode will load data from zephyr at first time and use that data for consequent command runs.
    | It's needed to not hit the api limit and save time in development/testing process
    |
    */

    'test_mode' => env('ZEPHYR_TEST_MODE'),

    /*
    |--------------------------------------------------------------------------
    | Max results that Zephyr should return
    |--------------------------------------------------------------------------
    | You might not need max results at all, but you need to specify amount anyway (APIs required parameter).
    | Set high value if you need to get all cases at once
    |
    */

    'max_test_results' => 1000,

    /*
    |--------------------------------------------------------------------------
    | base_url that zephyr api uses
    |--------------------------------------------------------------------------
    */

    'base_url' => env('ZEPHYR_BASE_URL', 'https://api.zephyrscale.smartbear.com/v2'),

];
