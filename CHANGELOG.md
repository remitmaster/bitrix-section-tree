# Changelog

Формат based on [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added
- `SectionTree::toTree()` — плоский список → дерево
- `SectionTree::toFlat()` — дерево → плоский список с `DEPTH`/`PATH`
- `SectionTree::breadcrumbs()` — цепочка предков по id

### Fixed
- `toTree()`/`breadcrumbs()`: id `0` (int) и `"0"` (string) в `parentKey` теперь ведут себя одинаково — раньше int `0` всегда трактовался как «нет родителя», даже если раздел с id `0` реально существовал в списке
- `toTree()`/`breadcrumbs()`: отсутствующий `idKey` в элементе теперь бросает `SectionTreeException` вместо тихих PHP-warning'ов и битого дерева
