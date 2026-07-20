# SectionTree package Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `yunaweb/bitrix-section-tree` — a framework-agnostic PHP package that converts a flat list (e.g. rows from `\Bitrix\Iblock\SectionTable::getList()`) into a tree, back into a flat list with depth/path metadata, and extracts an ancestor chain (breadcrumbs) by id.

**Architecture:** Single static utility class `Yunaweb\SectionTree\SectionTree` with three methods (`toTree`, `toFlat`, `breadcrumbs`) operating purely on plain PHP arrays — no Bitrix dependency. One exception type (`Yunaweb\SectionTree\Exception\SectionTreeException`) for all input-data errors (duplicate id, cycle, missing id). Full spec: `docs/superpowers/specs/2026-07-20-bitrix-section-tree-design.md`.

**Tech Stack:** PHP 8.1+, Composer (PSR-4), PHPUnit ^10.5.

---

## File Structure

```
bitrix-section-tree/
  composer.json
  CHANGELOG.md
  LICENSE
  README.md
  .gitignore
  phpunit.xml
  src/
    SectionTree.php
    Exception/SectionTreeException.php
  tests/
    SectionTreeTest.php
```

- `src/Exception/SectionTreeException.php` — the one exception type, no logic.
- `src/SectionTree.php` — all three public methods + private helpers (`assertNoDuplicateIds`, `assertNoCycles`, `flattenLevel`). One file: the class is small (three methods + three tiny private helpers), splitting further would be premature.
- `tests/SectionTreeTest.php` — one test class covering all three methods; small enough to stay in one file.

---

### Task 1: Scaffold package (composer, license, changelog, gitignore, phpunit config)

**Files:**
- Create: `composer.json`
- Create: `LICENSE`
- Create: `CHANGELOG.md`
- Create: `.gitignore`
- Create: `phpunit.xml`

- [ ] **Step 1: Write `composer.json`**

```json
{
    "name": "yunaweb/bitrix-section-tree",
    "description": "Build/flatten section trees from flat lists (e.g. Bitrix CIBlockSection / D7 SectionTable rows) without hierarchy hacks.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "Yunaweb\\SectionTree\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Yunaweb\\SectionTree\\Tests\\": "tests/"
        }
    }
}
```

- [ ] **Step 2: Write `LICENSE`** (MIT, copyright Ramil Yunaliev, current year 2026)

```
MIT License

Copyright (c) 2026 Ramil Yunaliev

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

- [ ] **Step 3: Write `CHANGELOG.md`**

```markdown
# Changelog

Формат based on [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added
- `SectionTree::toTree()` — плоский список → дерево
- `SectionTree::toFlat()` — дерево → плоский список с `DEPTH`/`PATH`
- `SectionTree::breadcrumbs()` — цепочка предков по id
```

- [ ] **Step 4: Write `.gitignore`**

```
/vendor/
composer.lock
.phpunit.result.cache
```

- [ ] **Step 5: Write `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 6: Install dependencies**

Run: `cd /Users/anastasiayunalieva/Work/MyProjects/packages/bitrix-section-tree && composer install`
Expected: `vendor/` created, `composer.lock` created, no errors.

- [ ] **Step 7: Commit**

```bash
git add composer.json LICENSE CHANGELOG.md .gitignore phpunit.xml composer.lock
git commit -m "chore: scaffold package (composer, license, changelog, phpunit)"
```

---

### Task 2: Exception type

**Files:**
- Create: `src/Exception/SectionTreeException.php`
- Test: `tests/SectionTreeTest.php` (new file)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Yunaweb\SectionTree\Tests;

use PHPUnit\Framework\TestCase;
use Yunaweb\SectionTree\Exception\SectionTreeException;

final class SectionTreeTest extends TestCase
{
    public function testExceptionIsInvalidArgumentException(): void
    {
        $this->assertInstanceOf(\InvalidArgumentException::class, new SectionTreeException('test'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit`
Expected: FAIL — `Class "Yunaweb\SectionTree\Exception\SectionTreeException" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Yunaweb\SectionTree\Exception;

final class SectionTreeException extends \InvalidArgumentException
{
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit`
Expected: PASS (1 test, 1 assertion)

- [ ] **Step 5: Commit**

```bash
git add src/Exception/SectionTreeException.php tests/SectionTreeTest.php
git commit -m "feat: add SectionTreeException"
```

---

### Task 3: `toTree()` — happy path (empty input, multi-level tree)

**Files:**
- Create: `src/SectionTree.php`
- Modify: `tests/SectionTreeTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `tests/SectionTreeTest.php` inside the `SectionTreeTest` class (add `use Yunaweb\SectionTree\SectionTree;` to the `use` block at top):

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit`
Expected: FAIL — `Class "Yunaweb\SectionTree\SectionTree" not found`

- [ ] **Step 3: Write minimal implementation**

```php
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit`
Expected: PASS (3 tests, N assertions)

- [ ] **Step 5: Commit**

```bash
git add src/SectionTree.php tests/SectionTreeTest.php
git commit -m "feat: add SectionTree::toTree() happy path"
```

---

### Task 4: `toTree()` — edge cases (duplicate id, dangling parent, cycle)

**Files:**
- Modify: `src/SectionTree.php`
- Modify: `tests/SectionTreeTest.php`

- [ ] **Step 1: Write the failing tests**

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit`
Expected: FAIL — `testToTreeAttachesOrphansToRootWhenParentMissing` passes already (orphan handling already works from Task 3 implementation), but `testToTreeThrowsOnDuplicateId`, `testToTreeThrowsOnSelfReferencingCycle`, `testToTreeThrowsOnIndirectCycle` FAIL — no exception thrown (duplicate silently overwrites, cycle causes the existing `toTree()` to build a self-referencing array without erroring, which PHPUnit will still let return but the assertion `expectException` fails since no exception was thrown).

- [ ] **Step 3: Write minimal implementation**

Replace `src/SectionTree.php` with:

```php
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit`
Expected: PASS (7 tests, N assertions)

- [ ] **Step 5: Commit**

```bash
git add src/SectionTree.php tests/SectionTreeTest.php
git commit -m "feat: validate duplicate ids and cycles in SectionTree::toTree()"
```

---

### Task 5: `toFlat()` — depth/path metadata, empty input, roundtrip

**Files:**
- Modify: `src/SectionTree.php`
- Modify: `tests/SectionTreeTest.php`

- [ ] **Step 1: Write the failing tests**

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit`
Expected: FAIL — `Call to undefined method Yunaweb\SectionTree\SectionTree::toFlat()`

- [ ] **Step 3: Write minimal implementation**

Add to `src/SectionTree.php`, inside the `SectionTree` class (after `toTree()`):

```php
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit`
Expected: PASS (10 tests, N assertions)

- [ ] **Step 5: Commit**

```bash
git add src/SectionTree.php tests/SectionTreeTest.php
git commit -m "feat: add SectionTree::toFlat()"
```

---

### Task 6: `breadcrumbs()` — ancestor chain, missing id, duplicate id guard

**Files:**
- Modify: `src/SectionTree.php`
- Modify: `tests/SectionTreeTest.php`

- [ ] **Step 1: Write the failing tests**

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit`
Expected: FAIL — `Call to undefined method Yunaweb\SectionTree\SectionTree::breadcrumbs()`

- [ ] **Step 3: Write minimal implementation**

Add to `src/SectionTree.php`, inside the `SectionTree` class (after `toFlat()` and its private helpers):

```php
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit`
Expected: PASS (14 tests, N assertions)

- [ ] **Step 5: Commit**

```bash
git add src/SectionTree.php tests/SectionTreeTest.php
git commit -m "feat: add SectionTree::breadcrumbs()"
```

---

### Task 7: README with usage examples

**Files:**
- Create: `README.md`

- [ ] **Step 1: Write `README.md`**

```markdown
# yunaweb/bitrix-section-tree

Собрать дерево разделов из плоского списка (например, из `\Bitrix\Iblock\SectionTable::getList()` или `CIBlockSection::GetList()`) — без ручного `unset`/`usort`-хака. Работает с обычными PHP-массивами, к Bitrix не привязан.

## Установка

```bash
composer require yunaweb/bitrix-section-tree
```

## Использование

### Плоский список → дерево

```php
use Yunaweb\SectionTree\SectionTree;

$sections = [
    ['ID' => 1, 'IBLOCK_SECTION_ID' => null, 'NAME' => 'Каталог'],
    ['ID' => 2, 'IBLOCK_SECTION_ID' => 1, 'NAME' => 'Телефоны'],
    ['ID' => 3, 'IBLOCK_SECTION_ID' => 1, 'NAME' => 'Ноутбуки'],
];

$tree = SectionTree::toTree($sections);
// $tree[0]['NAME'] === 'Каталог'
// $tree[0]['CHILDREN'][0]['NAME'] === 'Телефоны'
```

### Дерево → плоский список с глубиной и путём

```php
$flat = SectionTree::toFlat($tree);
// $flat[1]['DEPTH'] === 1
// $flat[1]['PATH'] === [1, 2]
```

### Цепочка предков (breadcrumbs)

```php
$chain = SectionTree::breadcrumbs($sections, 2);
// array_column($chain, 'NAME') === ['Каталог', 'Телефоны']
```

### С Bitrix D7

```php
use Bitrix\Iblock\SectionTable;
use Yunaweb\SectionTree\SectionTree;

$rows = SectionTable::getList([
    'filter' => ['IBLOCK_ID' => 8],
    'select' => ['ID', 'IBLOCK_SECTION_ID', 'NAME', 'SORT'],
])->fetchAll();

$tree = SectionTree::toTree($rows);
```

## Настраиваемые ключи

Если поля называются не как в Bitrix (`ID` / `IBLOCK_SECTION_ID`), передайте свои имена:

```php
SectionTree::toTree($items, idKey: 'id', parentKey: 'parent_id');
```

## Ошибки

Дублирующийся id, циклическая ссылка на родителя или `breadcrumbs()` с несуществующим id бросают `Yunaweb\SectionTree\Exception\SectionTreeException`.

## Тесты

```bash
composer install
vendor/bin/phpunit
```

## Лицензия

MIT
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add README with usage examples"
```

---

## Final Verification

- [ ] Run full test suite one more time: `vendor/bin/phpunit` — expect all tests PASS, no warnings.
- [ ] Run `composer validate` — expect no errors.
- [ ] Confirm `git log --oneline` shows one commit per task, working tree clean (`git status`).
