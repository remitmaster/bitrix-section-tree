# bitrix-section-tree — design

## Контекст

Пост `razdely-infobloka-v-vide-massiva-1s-bitriks.mdx` (2014, ramik-blog) собрал много комментариев: два процедурных способа собрать разделы инфоблока Bitrix (`CIBlockSection::GetList()`) в плоский массив с метаданными иерархии или в дерево. Код старый (Hungarian notation, глобальные функции, баги вроде неверной сортировки через `usort` по ключам с `unset`).

Задача: написать новый пост с современным подходом (D7) и вынести сборку дерева в отдельный composer-пакет — не завязанный на Bitrix, чтобы:
- пакет был тестируем без живого Bitrix (PHPUnit),
- пакет был переиспользуем вне Bitrix (вход/выход — обычные PHP-массивы),
- в посте показать, как пакет оборачивает реальный вызов `\Bitrix\Iblock\SectionTable::getList()`.

Репозиторий пакета: `/Users/anastasiayunalieva/Work/MyProjects/packages/bitrix-section-tree`, remote `git@github.com:remitmaster/bitrix-section-tree.git` (GitHub-аккаунт не меняется), composer package `yunaweb/bitrix-section-tree` — отдельно от `ramik-blog`.

## Скоуп

Один класс `SectionTree` (namespace `Yunaweb\SectionTree`), три статических метода:

```php
final class SectionTree
{
    public static function toTree(
        array $items,
        string $idKey = 'ID',
        string $parentKey = 'IBLOCK_SECTION_ID',
        string $childrenKey = 'CHILDREN'
    ): array;

    public static function toFlat(
        array $tree,
        string $childrenKey = 'CHILDREN'
    ): array;

    public static function breadcrumbs(
        array $items,
        int|string $id,
        string $idKey = 'ID',
        string $parentKey = 'IBLOCK_SECTION_ID'
    ): array;
}
```

Дефолты ключей соответствуют полям Bitrix (`ID`, `IBLOCK_SECTION_ID`), но переопределяемы — сам класс о Bitrix ничего не знает.

### `toTree()`
Плоский список → дерево. Каждый узел = исходные поля элемента + ключ `CHILDREN` (всегда присутствует, `[]` если детей нет). Порядок сиблингов — как в исходном массиве (сортировка по `SORT` — забота вызывающего, до вызова).

### `toFlat()`
Обратная операция: дерево (как из `toTree()`) → плоский список. К каждому элементу добавляются:
- `DEPTH` (int, у корня `0`),
- `PATH` (array ID от корня до узла включительно, сам узел — последний элемент).

`CHILDREN` в результате не оставляем — это уже плоский список.

### `breadcrumbs()`
Цепочка предков по ID: от корня до `$id` включительно, в виде списка исходных элементов (без добавленных ключей). Если `$id` не найден в `$items` — исключение.

## Обработка edge cases (явно, без молчаливых потерь данных)

| Ситуация | Поведение |
|---|---|
| Пустой `$items`/`$tree` | Вернуть `[]`, не исключение |
| `parent_id` = `null`/`0`/`''` | Элемент — корень |
| `parent_id` указывает на несуществующий ID (напр. родитель отфильтрован по `ACTIVE=Y`, а потомок остался) | Элемент уходит в корень дерева — не пропадает молча |
| Дублирующийся `ID` в `$items` | `SectionTreeException` — это ошибка данных вызывающего, скрывать нельзя |
| Цикл (`parent_id` элемента, прямо или через цепочку, указывает на самого себя) | `SectionTreeException` — иначе бесконечная рекурсия |
| `breadcrumbs()` на ID, которого нет в `$items` | `SectionTreeException` |

Одно исключение `Yunaweb\SectionTree\Exception\SectionTreeException extends \InvalidArgumentException` на все три случая — типизированный маркер "ошибка входных данных", details в message.

## Структура пакета

```
bitrix-section-tree/
  composer.json
  LICENSE (MIT)
  README.md
  .gitignore
  src/
    SectionTree.php
    Exception/SectionTreeException.php
  tests/
    SectionTreeTest.php
  phpunit.xml
```

`composer.json`: `name: yunaweb/bitrix-section-tree`, `require: php >=8.1`, `require-dev: phpunit/phpunit ^10.5`, PSR-4 autoload `Yunaweb\SectionTree\` → `src/`, dev-autoload `Yunaweb\SectionTree\Tests\` → `tests/`.

## Тесты (PHPUnit, без Bitrix-окружения)

- `toTree()`: пустой вход → `[]`
- `toTree()`: 2-уровневое дерево (несколько корней, у каждого дети)
- `toTree()`: 3+ уровня (внуки)
- `toTree()`: висячий `parent_id` → узел в корне
- `toTree()`: дубль `ID` → `SectionTreeException`
- `toTree()`: цикл → `SectionTreeException`
- `toFlat()`: roundtrip — `toFlat(toTree($items))` даёт исходные элементы + `DEPTH`/`PATH`, без потери и без порядка-мусора
- `toFlat()`: пустое дерево → `[]`
- `breadcrumbs()`: узел третьего уровня → цепочка из 3 элементов, корень первым
- `breadcrumbs()`: несуществующий ID → `SectionTreeException`

## Связь с постом (ramik-blog, отдельная задача)

- Новый пост в `ramik-blog/content/posts/` (slug ещё не зафиксирован) — старый способ (2014, массив-хак) оставляем как контекст/историю, добавляем D7-способ (`\Bitrix\Iblock\SectionTable::getList()`) и показываем `SectionTree::toTree()` на реальных данных.
- Технические факты про D7 (`SectionTable`, имена полей, фильтры) — сверить с офдокой (`ctx7`/`dev.1c-bitrix.ru`) перед публикацией, по правилу проекта (см. `feedback_verify_technical_posts.md` в памяти).
- Старый пост `razdely-infobloka-v-vide-massiva-1s-bitriks.mdx` — файл/URL не трогаем, добавляем ссылку на новый пост.
- Пакет — публичный composer/GitHub, ссылка на него из нового поста.

## Не входит в скоуп (явно)

- Никакой зависимости пакета от `\Bitrix\Iblock\SectionTable` или другого Bitrix-кода.
- Никакой сортировки по `SORT` внутри пакета.
- Никаких доп. операций сверх `toTree`/`toFlat`/`breadcrumbs` (поиск по имени, фильтрация и т.п.) — не запрошено, не добавляем.
