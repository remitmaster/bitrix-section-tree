<?php

declare(strict_types=1);

namespace Yunaweb\SectionTree;

final class SectionTree
{
    public static function toTree(
        array $items,
        string $idKey = 'ID',
        string $parentKey = 'IBLOCK_SECTION_ID',
        string $childrenKey = 'CHILDREN'
    ): array {
        if ($items === []) {
            return [];
        }

        $nodes = [];
        foreach ($items as $item) {
            $id = $item[$idKey];
            $nodes[$id] = $item;
            $nodes[$id][$childrenKey] = [];
        }

        $roots = [];
        foreach ($items as $item) {
            $id = $item[$idKey];
            $parentId = $item[$parentKey] ?? null;

            if ($parentId === null || $parentId === 0 || $parentId === '' || !array_key_exists($parentId, $nodes)) {
                $roots[] = &$nodes[$id];
            } else {
                $nodes[$parentId][$childrenKey][] = &$nodes[$id];
            }
        }

        return $roots;
    }
}
