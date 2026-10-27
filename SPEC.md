# SPEC: FastTemplate 1.6.2 — совместимость PHP 7.4–8.4

## Цель
Один файл `class.FastTemplate.php`, работающий на PHP 7.4–8.4 без deprecation-ворчаний.
Подход — минимальные точечные правки, без переписывания архитектуры (динамические
свойства `$this->$handle` — неотъемлемая часть дизайна класса).

Ответ на вопрос: **да, одна кодовая база под 7 и 8 возможна.** Уходить «только на 8.4»
не нужно: всё, что мешает PHP 8 — это удалённые PHP4-функции и пара синтаксических
пережитков. Никакого PHP8-only синтаксиса не требуется, обратная совместимость
с 7.4 сохраняется.

## Почему «как-то работала под 7.4»
Часть функций была удалена ещё в PHP 7.0 — библиотека жила на 7.4 только потому,
что соответствующие ветки кода не выполнялись (STRICT-режим, clear_dynamic, debug):

### Фатально сломано уже на PHP 7.0+ (функция удалена)
- `each()` — 17 мест (parse, parse_template, parse_defined, parse_dynamic,
  clear_dynamic, clear, clear_tpl, clear_define, clear_assign, assign,
  multiple_assign, define, printarray)
- `ereg()` — строки ~553 (`show_unknowns`), ~808 (`parse_template` STRICT-ветка)
- `split()` — строки ~809 (`parse_template`), ~1497 (`clear_dynamic`)
- `mysql_escape_string()` — строка ~2110 (`printarray`, debug)

### Фатально сломано на PHP 8.0
- PHP4-конструктор `function FastTemplate()` — в PHP 8 не считается конструктором
- `each()` — те же 17 мест
- `get_magic_quotes_gpc()` — удалена в 8.0 (getfast, ~961)
- `$field{0}` — curly-brace offset удалён в 8.0 (`value_defined`, ~606)

### Deprecations / предупреждения PHP 8.0–8.4
- Динамические свойства `$this->$handle`, `$this->PRE_FILTER` — deprecated с 8.2
  → `#[AllowDynamicProperties]` (в PHP 7.x парсится как `#`-комментарий — безопасно)
- `FastWrite($template = "", $outputfile)` — required-after-optional, deprecated в 8.0
- `str_replace(..., null, ...)` / `htmlspecialchars($obj)` — null/non-string в 8.1+
- `ord()` на пустой строке / несуществующих индексах `$regs[3]`, `$regs[4]`,
  `$ext[1]`, `$matches[1][$i]` — «Undefined array key» warnings
- `GLOBAL $php_errormsg` — track_errors удалён в 8.0 (мёртвый код, безвреден)
- `rewrite_link_href_callback()` объявлена ВНЕ guard `class_exists` —
  повторный include файла = fatal "Cannot redeclare function"

### Пре-существующие баги (не связаны с версией PHP, исправим заодно)
- `DELETE_CACHE()`: `while (($fname = readdir($dir)) != true)` — логика инвертирована,
  цикл пропускает реальные файлы; + `filemtime` на `.`/`..`, + `$ext[1]` без isset
- `emptyPattern()`: `$this->PATTERN_VARS_DEFINE[] = array()` — ДОБАВЛЯЕТ пустой
  элемент вместо очистки массива
- `showDebugInfo()`: ветка `$Debug_type == 2` недостижима (условия инвертированы)
- `parse_template()`: объект из PARSEVARS без `__toString` в str_replace-цикле
  → fatal "could not be converted to string" на PHP 8
- `$_SERVER['REQUEST_URI']` / `HTTP_USER_AGENT` без проверки — notice в CLI
- `self_script()`: `$q[]` без инициализации
- `set_root('')`: `ord(false)` → TypeError на PHP 8

## План изменений (единственный файл: class.FastTemplate.php)

1. `function FastTemplate()` → `function __construct()` (+ метод-алиас
   `FastTemplate()` для BC, вызывает `__construct`)
2. `#[AllowDynamicProperties]` перед `class FastTemplate` + объявить
   `var $PRE_FILTER = array();`
3. Все `while (list($k,$v) = each($arr))` → `foreach ($arr as $k => $v)`
4. `each($GLOBALS)` → `foreach (array_keys($GLOBALS) as $key)` (итерация по копии,
   т.к. внутри мутируются глобалы)
5. `ereg` → `preg_match`, `split` → `explode`, `mysql_escape_string` → `addslashes`
6. `get_magic_quotes_gpc()` — убрать вызов; `stripslashes` безусловно
   (magic quotes отключены с PHP 5.4 — поведение идентично)
7. `$field{0}` → проверка через `$field !== '' && $field[0] === '.'`
8. `FastWrite($template = "", $outputfile = "")`
9. Защиты от undefined index / null: `$regs[3] ?? ''`, `isset()` в
   parseParamString, value_defined, DELETE_CACHE, `$_SERVER[...] ?? ''`
10. `is_scalar`-guard перед `str_replace`/`htmlspecialchars` (null deprecation 8.1,
    fatal на объектах без `__toString`)
11. Фиксы багов: DELETE_CACHE (`!== false`, skip `.`/`..`, `$ext[1]` isset),
    emptyPattern (`= array()`), showDebugInfo (`if/elseif`), `strstr(...) ?: keep`,
    перенос `rewrite_link_href_callback` под guard / `function_exists`
12. `utime()` → `return microtime(true);` (идентичная семантика)
13. Убрать `GLOBAL $php_errormsg` (мёртвый код)

## Что НЕ меняем
- Стиль кода (CAPS-ключевые слова, `var`, табы) — минимальный diff
- `eval()` для `{Object.method(params)}` — работает на всех версиях, менять рискованно
- `var`-объявления свойств — валидны на 5.x–8.x; типизированные свойства сломали бы 7.x
- Публичный API и семантика шаблонов — без изменений

## Верификация
- `php -l` на PHP 7.4 (локально доступно)
- Smoke-тест: define/assign/parse/fetch + dynamic block + IFDEF на тестовом шаблоне
- Рекомендация: прогон на PHP 8.x, если есть в OpenServer
