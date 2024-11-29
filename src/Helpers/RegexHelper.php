<?php

namespace RedberryProducts\Zephyr\Helpers;

class RegexHelper
{
    public static function buildTestFilesExecutionFilterRegex(array $testCaseIds): string
    {
        // Escape IDs for regex and join with OR (|)
        $escapedIds = array_map('preg_quote', $testCaseIds);
        $pattern = implode('|', $escapedIds);

        // Return the full regex
        return "/^($pattern)-.*\\.php$/";
    }
}
