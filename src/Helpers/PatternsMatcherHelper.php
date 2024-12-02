<?php

namespace RedberryProducts\Zephyr\Helpers;

class PatternsMatcherHelper
{
    public static function buildTestFilesIdsParameterForCLI(array $testCaseIds): string
    {
        $implodedString = implode(' ', $testCaseIds);

        return escapeshellarg($implodedString);
    }

    // TODO@kosta: remove this function, we don't need this
    public static function getTestFileIdMatcherPattern(string $projectKey): string
    {
        return "/\[\s*(" . preg_quote($projectKey, '/') . "-T\d+(\s*,\s*" . preg_quote($projectKey, '/') . "-T\d+)*)\s*\]/";
    }
}
