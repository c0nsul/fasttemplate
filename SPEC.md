# SPEC: FastTemplate 1.7.0 — совместимость PHP 7.4–8.4

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

---

# РАУНД 2 — ревью после миграции (новые находки)

## Критические (PHP 8: TypeError/ValueError)
- `rewrite_src_path()`: `sizeof($this->REWRITE_SRC_PATH)` на строке → TypeError 8.0
  → `if (!empty(...) && is_string(...))`
- `parse_template()`: `$matches[4][$i]` без isset (тег `{Obj.method}` без скобок) →
  `parseParamString(NULL)` → TypeError; → `isset(...) ? ... : ''`
- `parse_template()`: вход `FALSE` (get_template упал) → `preg_match_all(..., FALSE)`
  → TypeError; → `$template = (string)$template;` в начале
- `FastWrite()`: `fopen('', 'w')` при вызове без аргументов → ValueError;
  → guard на пустой `$outputfile`
- `printarray()`: `count(NULL)` после `clear_define()` (FILELIST unset) → TypeError;
  → `is_array($arr) && count($arr) != 0`
- `parseParamString()`: объект в PARSEVARS внутри параметров → `str_replace(..., $obj, ...)`
  → TypeError; → is_scalar-guard
- `get_template()`: `rewrite_src_path(FALSE)` при упавшем include + включённом rewrite
  → TypeError; → проверка `$contents` до rewrite

## Deprecations 8.1+
- `FastPrint()`/`FastWrite()`: `getfast()` возвращает NULL при ошибке →
  `str_replace(..., NULL)` / `fwrite($fp, NULL)`; → null-guard

## Undefined index / warnings
- `parse()`: `$this->FILELIST["$val"]` без isset (хендл не определён)
- `parse_dynamic()`/`clear_dynamic()`: `$this->DYNAMIC["$Macro"]` без isset
- `parse_defined()`: `ENDIF` без `IFDEF` → `$depth` < 0 → `$needparsedef[-1]`
- `get_template()`: `include` несуществующего файла → warning; → `file_exists()`

## Поведенческие (унаследованные)
- `getfast()`: `stripslashes()` — рудимент magic_quotes, портит `\` в выводе → убрать
- `pre_filter()`: `PRE_FILTER` заполняется, но нигде не применяется → применить в parse_template
- `error()`: всегда `echo "ERROR: ... <BR>"` → утечка в вывод/CLI; → флаг/error_log
- `parse()`: `empty(FALSE)` → повторная попытка загрузки упавшего шаблона на каждый parse

## Безопасность
- `eval()` с именем метода из шаблона (parse_template, value_defined) → RCE при
  пользовательских шаблонах; → валидация имени `^[a-zA-Z_][a-zA-Z0-9_]*$` перед eval
- `include $fname` для `.ft` кэша → выполнение PHP из cache-дира; → `readfile()`

## Оптимизации
- `parse_template()`: O(vars × template) str_replace → один проход preg_replace_callback
  (опционально, BC-риск — literal vs regex)
- Мёртвые переменные: `$ifdefs`/`$needparsedef` в parse_template, `$ifdefs` в parse_defined
- `cache_file_is_updated()`: двойной `filemtime`

---

# РАУНД 3 — анализ производительности (benchmark, PHP 7.4.25)

## Замеры (30 итераций, template ~370KB, 600 переменных)
- `parse_template()` 600 vars: **44.1 ms/op** — главный хотспот
- `parse_template()` 20 vars: 6.7 ms/op — почти линейно по числу vars
- `strtr()` тот же объём за 1 проход: **0.49 ms/op** — НО это стоимость
  ТОЛЬКО компонента подстановки, не всего parse_template
- `str_replace()` массивами: 34.9 ms/op — НЕ решение (те же N сканов внутри)
- IFDEF-heavy (48KB, 600 блоков): 7.7 ms/op, из них `parse_defined()` ~1.5 ms/op
- `parse()` end-to-end (file + 600 vars): 43.0 ms/op ≈ стоимость parse_template
- Memory: стабильно ~6 MB peak, утечек нет
- Честная цель O1 end-to-end: **44 → ~5-7 ms (7-9×)**; с O2b на шаблонах без
  маркеров: ~2-3 ms (15-20×). «<2 ms» относилось только к компоненту.

## Карта проходов по шаблону за один parse_template()
1. preg_match_all object-tags — 1 скан (всегда)
2. preg_match_all include-tags — 1 скан (всегда)
3. **FOREACH vars: str_replace на КАЖДУЮ переменную — N сканов** ← хотспот
4. preg_replace чистка незакрытых {VAR} — 1 скан
5. 4× preg_replace нормализация IFDEF/IFNDEF/ELSE/ENDIF — 4 скана (всегда!)
6. explode+foreach удаление DYNAMIC-маркеров — 1 скан (всегда!)
7. parse_defined(): explode+foreach+regex на каждую строку — 1-2 скана (всегда!)

Итого: **N+9 сканов шаблона**, даже если в нём нет ни IFDEF, ни dynamic, ни object-тегов.

## Скрытые трансформации вывода (выяснены при ревизии плана)
- `parse_defined()` делает `trim()` КАЖДОЙ строки (SPEC:737) — индентация
  срезается (видно в `<pre>`, `<textarea>`)
- dynamic-strip (`parse_template`:874-892): explode/rebuild добавляет **+1
  trailing "\n"** на каждый вызов; `parse_defined()` добавляет ещё +1
  (gate:736 дропает строки с literal "IFDEF:"/"IFNDEF:"/"ELSE"/"ENDIF" —
  без `<!--`, case-sensitive)
- Вывод: любой «пропуск» этих этапов = байтовое изменение вывода.

## Ранжированные оптимизации (в порядке выгоды, исправлено)
- **O1. Подстановка переменных через strtr() + bounded do-while** — карта
  строится с ТЕМИ ЖЕ фильтрами (empty-key skip, arrays/objects w-o
  `__toString` skip, `(string)`-cast, NULL→''); ключи `'{K}'` не пересекаются
  (брейсы delim), longest-match == sequential здесь. Транзитивная подстановка
  НЕ опция, а обязательное поведение: assign() допускает значения с
  placeholders (`assign('TITLE','Hello {NAME}')`), и nested-результаты в
  PARSEVARS иногда тоже. Одиночный strtr этого не даёт → нужен do-while
  `while changed` с cap=count(map) (защита от циклов A↔B; остаток циклов
  всё равно съедает cleanup-regex, финальный вывод тот же).
  Гейт: если ни одно значение не содержит '{' — ровно 1 проход.
  Расхождение с текущим кодом: backward-ref (значение поздней var
  ссылается на раннюю — текущий цикл молча дропает, do-while резолвит).
  Forward-ref (на позднюю var) текущий код и так резолвит — паритет.
  Флаг `FAST_REPLACE`: TRUE (default) = strtr-путь, FALSE = старый
  последовательный цикл — BC-параноикам; держим обе ветки.
- **O2a. Guard 4× preg_replace по `strpos('<!--')`** — байтово идентично
  (без `<!--` эти regex не могут ничего изменить). BC-риск: нулевой.
- **O2b. Skip dynamic-strip + parse_defined при отсутствии `<!--`** —
  ВНИМАНИЕ: НЕ байтово-идентично! Убирает случайный trim строк и +2
  trailing "\n" на вызов. По сути багфикс (trim портит pre/textarea,
  мусорные \n накапливаются при append `.row`), но вывод меняется.
  Принимать осознанно (changelog + byte-diff в задаче 38) или за флагом.
- **O3. parse_defined(): fast-path по строкам** — при `strpos($line,'<!--')
  === false` скипать 4× preg_match (740/748/756/761), но НЕ gate strpos
  (736) — он держит текущую семантику дропа "ELSE"/"ENDIF"-строк. BC-safe.
- **O4. parse_dynamic() strpos fast-path** — ВЫПАДАЕТ: там уже substr_count
  (та же семья), реальной выгоды нет.
- **O5. Guard include-regex по `strpos('#include')`** — полезно; guard '{'
  для object-regex почти бесполезен (брейсы в шаблонах почти всегда есть).
  «Ранний выход, если нет '{' И '<!--'» — НЕ байтово-безопасен (пропускает
  trim+trailing \n), входит в решение O2b, а не в O5.
- **O6/O7.** мелочь, по желанию.

## Вне скоупа (зафиксировано)
- `multiple_assign()`: при активных patterns — foreach по ВСЕМ $GLOBALS на
  каждый parse() — потенциально квадратично на больших приложениях.
  Отмечено, не меняем.
- Кэширование разобранных шаблонов, preg_replace_callback вместо strtr —
  отклонено (семантика/сложность).
