<?php

namespace RedberryProducts\Zephyr\Traits;

trait TestPatternMatcherTrait
{
    public function getTestIdPattern(string $projectKey): string
    {
        return "/\[\s*(" . preg_quote($projectKey, '/') . "-T\d+(\s*,\s*" . preg_quote($projectKey, '/') . "-T\d+)*)\s*\]/";
    }
}
