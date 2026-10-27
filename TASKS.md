# TASKS: PHP 7.4–8.4 compat — class.FastTemplate.php

## Раунд 1 (выполнен)
- [x] 1. Конструктор: `FastTemplate()` → `__construct()` + BC-алиас; убрать `GLOBAL $php_errormsg`
- [x] 2. `#[AllowDynamicProperties]` на класс + объявить `var $PRE_FILTER`
- [x] 3. `each()` → `foreach` (17 мест: parse, parse_template, parse_defined, parse_dynamic, clear_dynamic, clear, clear_tpl, clear_define, clear_assign, assign, multiple_assign/$GLOBALS, define, printarray)
- [x] 4. `ereg`→`preg_match`, `split`→`explode`, `mysql_escape_string`→`addslashes`, убрать `get_magic_quotes_gpc()`
- [x] 5. `$field{0}` → `$field !== '' && $field[0] == '.'` с проверкой пустой строки
- [x] 6. `FastWrite`: `$outputfile = ""` (required-after-optional)
- [x] 7. Undefined index защиты: `$regs[3]/[4] ?? ''`, PARSEVARS isset, `$_SERVER`, `$ext[1]`, `$q` init, `set_root('')`
- [x] 8. is_scalar-защиты для str_replace/htmlspecialchars (null-деприкейшены 8.1, объекты)
- [x] 9. Баги: DELETE_CACHE loop, emptyPattern, showDebugInfo, strstr-guard, rewrite_link_href_callback guard
- [x] 10. `utime()` → `microtime(true)`
- [x] 11. `php -l` + smoke-тест (define/assign/parse/fetch/dynamic/IFDEF) — пройден
- [x] 12. Финальное ревью по чек-листу — пройдено

## Раунд 2 (выполнен)
- [x] 13. `rewrite_src_path()`: `sizeof()` на строке → `!empty() && is_string()` (TypeError 8.0)
- [x] 14. `parse_template()`: `$matches[4][$i]` → isset-guard (TypeError 8.0)
- [x] 15. `parse_template()`: `$template = (string)$template;` в начале (вход FALSE → TypeError 8.0)
- [x] 16. `FastWrite()`: guard на пустой `$outputfile` (fopen('') → ValueError 8.0)
- [x] 17. `printarray()`: `is_array($arr) && count($arr) != 0` (count(NULL) → TypeError 8.0)
- [x] 18. `parseParamString()`: is_scalar-guard для значений PARSEVARS (объект → TypeError 8.0)
- [x] 19. `get_template()`: проверка `$contents` до `rewrite_src_path()` (FALSE → TypeError 8.0)
- [x] 20. `FastPrint()`/`FastWrite()`: null-guard на результат `getfast()` (deprecation 8.1)
- [x] 21. `parse()`: isset-guard на `$this->FILELIST["$val"]` (undefined index)
- [x] 22. `parse_dynamic()`/`clear_dynamic()`: isset-guard на `$this->DYNAMIC["$Macro"]`
- [x] 23. `parse_defined()`: guard `$depth` < 0 (ENDIF без IFDEF)
- [x] 24. `get_template()`: `file_exists()` перед include (warning noise)
- [x] 25. `getfast()`: убрать `stripslashes()` (рудимент magic_quotes, портит `\`)
- [x] 26. `pre_filter()`: применить `PRE_FILTER` в `parse_template()`
- [x] 27. `error()`: не echo'ить HTML в CLI/продакшн (флаг `ERROR_ECHO` или error_log)
- [x] 28. `parse()`: не ретраить загрузку упавшего шаблона (связано с #15)
- [x] 29. Безопасность: валидация имени метода `^[a-zA-Z_][a-zA-Z0-9_]*$` перед `eval()`
- [x] 30. Безопасность: `readfile()` вместо `include` для `.ft` кэша (verify_cached_files, cache_file)
- [x] 31. Оптимизация: убрать мёртвые `$ifdefs`/`$needparsedef`
- [x] 32. `php -l` + smoke-тест раунда 2 + финальное ревью — пройдено

## Раунд 3 — оптимизация (выполнен, проверен byte-diff + бенчмарком)
- [x] 33. O1: `parse_template()` — strtr-подстановка с bounded do-while
      + var `FAST_REPLACE` (default TRUE). Итог: 44 → 3.6 ms (12×),
      E2E parse(): 43 → 4.1 ms (10×)
- [x] 34. O2a: guard `strpos('<!--')` для 4× preg_replace IFDEF-семейства
- [x] 35. O2b: за флагом `FAST_BLOCK_SKIP` (default FALSE = байтово-старое).
      bs=1 на шаблоне без маркеров: 1.05 ms (43×)
- [x] 36. O3: `parse_defined()` — fast-path строк без `<!--` (4× preg_match)
- [x] 37. O5: guard include-regex по `strpos('#include')`; ранний выход
      отменён (не байтово-безопасен)
- [x] 38. Верификация: byte-diff vs git HEAD — orig==leg полностью;
      newdef отличается только backward-ref (задокументировано);
      skip отличается только whitespace (задокументировано).
      php -l OK, бенчмарк прогнан. Харнесс: `_bench/diff.php`
