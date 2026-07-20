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

    public static function toFlat(
        array $tree,
        string $idKey = 'ID',
        string $childrenKey = 'CHILDREN'
    ): array {
        $result = [];
        self::flattenLevel($tree, $idKey, $childrenKey, [], $result);

        return $result;
    }

    private static function flattenLevel(
        array $nodes,
        string $idKey,
        string $childrenKey,
        array $ancestorPath,
        array &$result
    ): void {
        foreach ($nodes as $node) {
            $children = $node[$childrenKey] ?? [];
            unset($node[$childrenKey]);

            $path = $ancestorPath;
            $path[] = $node[$idKey];

            $node['DEPTH'] = count($path) - 1;
            $node['PATH'] = $path;

            $result[] = $node;

            if ($children !== []) {
                self::flattenLevel($children, $idKey, $childrenKey, $path, $result);
            }
        }
    }

    public static function breadcrumbs(
        array $items,
        int|string $id,
        string $idKey = 'ID',
        string $parentKey = 'IBLOCK_SECTION_ID'
    ): array {
        self::assertNoDuplicateIds($items, $idKey);

        $nodes = [];
        foreach ($items as $item) {
            $nodes[$item[$idKey]] = $item;
        }

        if (!array_key_exists($id, $nodes)) {
            throw new SectionTreeException(sprintf('Section id "%s" not found.', (string) $id));
        }

        // Own cycle guard instead of assertNoCycles(): only this one chain needs
        // checking (not the whole dataset), and the message can name the id the
        // walk actually looped back to, not just the chain's starting id.
        $chain = [];
        $currentId = $id;
        $seen = [];

        while ($currentId !== null && array_key_exists($currentId, $nodes)) {
            if (isset($seen[$currentId])) {
                throw new SectionTreeException(sprintf('Cycle detected involving section id "%s".', (string) $currentId));
            }
            $seen[$currentId] = true;

            $chain[] = $nodes[$currentId];

            $currentId = $nodes[$currentId][$parentKey] ?? null;
            if ($currentId === 0 || $currentId === '') {
                $currentId = null;
            }
        }

        return array_reverse($chain);
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
