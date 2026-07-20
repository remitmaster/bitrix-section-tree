<?php

declare(strict_types=1);

namespace Yunaweb\SectionTree;

use Yunaweb\SectionTree\Exception\SectionTreeException;

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

        self::assertNoDuplicateIds($items, $idKey);
        self::assertNoCycles($items, $idKey, $parentKey);

        $nodes = [];
        foreach ($items as $item) {
            $id = $item[$idKey];
            $nodes[$id] = $item;
            $nodes[$id][$childrenKey] = [];
        }

        // By-reference: a later append to $nodes[$id][$childrenKey] must be visible
        // through whichever array already holds this node (root or a parent's children).
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

    private static function assertNoDuplicateIds(array $items, string $idKey): void
    {
        $seen = [];
        foreach ($items as $item) {
            $id = $item[$idKey];
            if (isset($seen[$id])) {
                throw new SectionTreeException(sprintf('Duplicate section id "%s".', (string) $id));
            }
            $seen[$id] = true;
        }
    }

    private static function assertNoCycles(array $items, string $idKey, string $parentKey): void
    {
        $parentOf = [];
        foreach ($items as $item) {
            $parentOf[$item[$idKey]] = $item[$parentKey] ?? null;
        }

        foreach ($parentOf as $id => $parentId) {
            $seen = [$id => true];

            while ($parentId !== null && $parentId !== 0 && $parentId !== '' && array_key_exists($parentId, $parentOf)) {
                if (isset($seen[$parentId])) {
                    throw new SectionTreeException(sprintf('Cycle detected involving section id "%s".', (string) $id));
                }
                $seen[$parentId] = true;
                $parentId = $parentOf[$parentId];
            }
        }
    }
}
