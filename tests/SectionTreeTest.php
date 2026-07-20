<?php

declare(strict_types=1);

namespace Yunaweb\SectionTree\Tests;

use PHPUnit\Framework\TestCase;
use Yunaweb\SectionTree\Exception\SectionTreeException;
use Yunaweb\SectionTree\SectionTree;

final class SectionTreeTest extends TestCase
{
    public function testExceptionIsInvalidArgumentException(): void
    {
        $this->assertInstanceOf(\InvalidArgumentException::class, new SectionTreeException('test'));
    }

    public function testToTreeReturnsEmptyArrayForEmptyInput(): void
    {
        $this->assertSame([], SectionTree::toTree([]));
    }

    public function testToTreeBuildsMultiLevelTree(): void
    {
        $items = [
            ['ID' => 1, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'Root A'],
            ['ID' => 2, 'IBLOCK_SECTION_ID' => 1, 'NAME' => 'Child A1'],
            ['ID' => 3, 'IBLOCK_SECTION_ID' => 1, 'NAME' => 'Child A2'],
            ['ID' => 4, 'IBLOCK_SECTION_ID' => 2, 'NAME' => 'Grandchild A1a'],
            ['ID' => 5, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'Root B'],
        ];

        $tree = SectionTree::toTree($items);

        $this->assertCount(2, $tree);
        $this->assertSame('Root A', $tree[0]['NAME']);
        $this->assertCount(2, $tree[0]['CHILDREN']);
        $this->assertSame('Child A1', $tree[0]['CHILDREN'][0]['NAME']);
        $this->assertCount(1, $tree[0]['CHILDREN'][0]['CHILDREN']);
        $this->assertSame('Grandchild A1a', $tree[0]['CHILDREN'][0]['CHILDREN'][0]['NAME']);
        $this->assertSame([], $tree[0]['CHILDREN'][0]['CHILDREN'][0]['CHILDREN']);
        $this->assertSame('Child A2', $tree[0]['CHILDREN'][1]['NAME']);
        $this->assertSame('Root B', $tree[1]['NAME']);
        $this->assertSame([], $tree[1]['CHILDREN']);
    }
}
