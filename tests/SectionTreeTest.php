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

    public function testToTreeAttachesOrphansToRootWhenParentMissing(): void
    {
        $items = [
            ['ID' => 1, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'Root'],
            ['ID' => 2, 'IBLOCK_SECTION_ID' => 999, 'NAME' => 'Orphan'],
        ];

        $tree = SectionTree::toTree($items);

        $this->assertCount(2, $tree);
        $this->assertSame('Root', $tree[0]['NAME']);
        $this->assertSame('Orphan', $tree[1]['NAME']);
    }

    public function testToTreeThrowsOnDuplicateId(): void
    {
        $items = [
            ['ID' => 1, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'First'],
            ['ID' => 1, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'Duplicate'],
        ];

        $this->expectException(SectionTreeException::class);

        SectionTree::toTree($items);
    }

    public function testToTreeThrowsOnSelfReferencingCycle(): void
    {
        $items = [
            ['ID' => 1, 'IBLOCK_SECTION_ID' => 1, 'NAME' => 'SelfParent'],
        ];

        $this->expectException(SectionTreeException::class);

        SectionTree::toTree($items);
    }

    public function testToTreeThrowsOnIndirectCycle(): void
    {
        $items = [
            ['ID' => 1, 'IBLOCK_SECTION_ID' => 2, 'NAME' => 'A'],
            ['ID' => 2, 'IBLOCK_SECTION_ID' => 1, 'NAME' => 'B'],
        ];

        $this->expectException(SectionTreeException::class);

        SectionTree::toTree($items);
    }

    public function testToFlatReturnsEmptyArrayForEmptyTree(): void
    {
        $this->assertSame([], SectionTree::toFlat([]));
    }

    public function testToFlatAddsDepthAndPath(): void
    {
        $tree = [
            [
                'ID' => 1,
                'NAME' => 'Root',
                'CHILDREN' => [
                    [
                        'ID' => 2,
                        'NAME' => 'Child',
                        'CHILDREN' => [
                            ['ID' => 3, 'NAME' => 'Grandchild', 'CHILDREN' => []],
                        ],
                    ],
                ],
            ],
        ];

        $flat = SectionTree::toFlat($tree);

        $this->assertCount(3, $flat);
        $this->assertSame(0, $flat[0]['DEPTH']);
        $this->assertSame([1], $flat[0]['PATH']);
        $this->assertArrayNotHasKey('CHILDREN', $flat[0]);
        $this->assertSame(1, $flat[1]['DEPTH']);
        $this->assertSame([1, 2], $flat[1]['PATH']);
        $this->assertSame(2, $flat[2]['DEPTH']);
        $this->assertSame([1, 2, 3], $flat[2]['PATH']);
    }

    public function testToFlatIsInverseOfToTree(): void
    {
        $items = [
            ['ID' => 1, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'Root A'],
            ['ID' => 2, 'IBLOCK_SECTION_ID' => 1, 'NAME' => 'Child A1'],
            ['ID' => 3, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'Root B'],
        ];

        $flat = SectionTree::toFlat(SectionTree::toTree($items));

        $names = array_column($flat, 'NAME');
        $this->assertSame(['Root A', 'Child A1', 'Root B'], $names);
        $this->assertSame([1], $flat[0]['PATH']);
        $this->assertSame([1, 2], $flat[1]['PATH']);
        $this->assertSame([3], $flat[2]['PATH']);
    }

    public function testBreadcrumbsReturnsChainFromRootToTarget(): void
    {
        $items = [
            ['ID' => 1, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'Root'],
            ['ID' => 2, 'IBLOCK_SECTION_ID' => 1, 'NAME' => 'Child'],
            ['ID' => 3, 'IBLOCK_SECTION_ID' => 2, 'NAME' => 'Grandchild'],
            ['ID' => 4, 'IBLOCK_SECTION_ID' => 1, 'NAME' => 'Unrelated'],
        ];

        $chain = SectionTree::breadcrumbs($items, 3);

        $this->assertSame(['Root', 'Child', 'Grandchild'], array_column($chain, 'NAME'));
    }

    public function testBreadcrumbsThrowsWhenIdNotFound(): void
    {
        $items = [
            ['ID' => 1, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'Root'],
        ];

        $this->expectException(SectionTreeException::class);

        SectionTree::breadcrumbs($items, 999);
    }

    public function testBreadcrumbsThrowsOnDuplicateId(): void
    {
        $items = [
            ['ID' => 1, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'First'],
            ['ID' => 1, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'Duplicate'],
        ];

        $this->expectException(SectionTreeException::class);

        SectionTree::breadcrumbs($items, 1);
    }

    public function testBreadcrumbsThrowsOnCycle(): void
    {
        $items = [
            ['ID' => 1, 'IBLOCK_SECTION_ID' => 2, 'NAME' => 'A'],
            ['ID' => 2, 'IBLOCK_SECTION_ID' => 1, 'NAME' => 'B'],
        ];

        $this->expectException(SectionTreeException::class);

        SectionTree::breadcrumbs($items, 1);
    }
}
