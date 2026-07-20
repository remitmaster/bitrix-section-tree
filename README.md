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

### Пример: `<select>` с отступами по уровню вложенности

```php
$flat = SectionTree::toFlat(SectionTree::toTree($sections));

foreach ($flat as $section) {
    $indent = str_repeat('— ', $section['DEPTH']);
    echo "<option value=\"{$section['ID']}\">{$indent}{$section['NAME']}</option>\n";
}
```

### Пример: рекурсивное меню (вложенные `<ul>`)

```php
function renderMenu(array $items): string
{
    $html = '<ul>';
    foreach ($items as $item) {
        $html .= '<li>' . htmlspecialchars($item['NAME']);
        if ($item['CHILDREN'] !== []) {
            $html .= renderMenu($item['CHILDREN']);
        }
        $html .= '</li>';
    }

    return $html . '</ul>';
}

echo renderMenu(SectionTree::toTree($sections));
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
