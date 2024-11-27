<?php

namespace RedberryProducts\Zephyr\Helpers;

/*
 * This class will create structure of folders & test cases that should be created on filesystem
 */
class TestsStructureArrayBuilder
{
    private array $folders;

    private array $testCases;

    private array $items = [];

    private array $structure = [];

    public function __construct(array $folders, array $testCases)
    {
        $this->folders = $folders;
        $this->testCases = $testCases;
    }

    public function build(): array
    {
        $this->initializeItems();
        $this->populateChildrenAndRoot();
        $this->assignTestCases();

        return $this->structure;
    }

    private function initializeItems(): void
    {
        foreach ($this->folders as $folder) {
            $folder += ['children' => [], 'test_cases' => []];
            $this->items[$folder['id']] = $folder;
        }
    }

    private function populateChildrenAndRoot(): void
    {
        foreach ($this->items as &$item) {
            if ($item['parentId'] !== null) {
                $this->items[$item['parentId']]['children'][] = &$item;
            } else {
                $this->structure['children'][] = &$item;
            }
        }
    }

    private function assignTestCases(): void
    {
        foreach ($this->testCases as $testCase) {
            if (is_null($testCase['folder'])) {
                $this->structure['test_cases'][] = $testCase;
            } else {
                $this->items[$testCase['folder']['id']]['test_cases'][] = $testCase;
            }
        }
    }
}
