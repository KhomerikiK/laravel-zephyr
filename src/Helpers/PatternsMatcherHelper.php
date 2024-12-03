<?php

namespace RedberryProducts\Zephyr\Helpers;

class PatternsMatcherHelper
{
    public static function buildTestFilesIdsParameterForCLI(array $testCaseIds): string
    {
        $implodedString = implode(' ', $testCaseIds);

        return escapeshellarg($implodedString);
    }

}
