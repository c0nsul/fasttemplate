# TASKS: PHP 7.4–8.4 compat — class.FastTemplate.php

- [ ] 1. Конструктор: `FastTemplate()` → `__construct()` + BC-алиас; убрать `GLOBAL $php_errormsg`
- [ ] 2. `#[AllowDynamicProperties]` на класс + объявить `var $PRE_FILTER`
- [ ] 3. `each()` → `foreach` (17 мест: parse, parse_template, parse_defined, parse_dynamic, clear_dynamic, clear, clear_tpl, clear_define, clear_assign, assign, multiple_assign/$GLOBALS, define, printarray)
- [ ] 4. `ereg`→`preg_match`, `split`→`explode`, `mysql_escape_string`→`addslashes`, убрать `get_magic_quotes_gpc()`
- [ ] 5. `$field{0}` → `$field[0]` с проверкой пустой строки
- [ ] 6. `FastWrite`: `$outputfile = ""` (required-after-optional)
- [ ] 7. Undefined index защиты: `$regs[3]/[4] ?? ''`, PARSEVARS isset, `$_SERVER`, `$ext[1]`, `$q` init, `set_root('')`
- [ ] 8. is_scalar-защиты для str_replace/htmlspecialchars (null-деприкейшены 8.1, объекты)
- [ ] 9. Баги: DELETE_CACHE loop, emptyPattern, showDebugInfo, strstr-guard, rewrite_link_href_callback guard
- [ ] 10. `utime()` → `microtime(true)`
- [ ] 11. `php -l` + smoke-тест (define/assign/parse/fetch/dynamic/IFDEF)
- [ ] 12. Финальное ревью по чек-листу
