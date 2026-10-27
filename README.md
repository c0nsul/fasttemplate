# FastTemplate

A fast, file-based template engine for PHP — modernized for **PHP 7.4–8.4**.

Originally a PHP3 port (1999, CDI) of the Perl module `CGI::FastTemplate` by
Jason Moore. Version **1.7.0** brings the classic 1.6.2 codebase up to date:
full PHP 8.x compatibility, bug fixes, security hardening and a major
performance overhaul — without breaking the public API.

```php
require 'class.FastTemplate.php';

$tpl = new FastTemplate('./templates');
$tpl->define('main', 'main.html');
$tpl->assign('NAME', 'World');
$tpl->parse('MAIN', 'main');
$tpl->FastPrint('MAIN');
```

## Features

- **Variable interpolation** — `{NAME}` placeholders, including nested values
- **Conditional blocks** — `<!-- IFDEF: VAR --> ... <!-- ELSE --> ... <!-- ENDIF -->`
- **Dynamic blocks** — repeating rows (`BEGIN DYNAMIC BLOCK`) for loops/DB results
- **Compound & append parsing** — nest templates, `parse('MAIN', '.row')` append mode
- **Object tags** — `{OBJ.method}`, `{OBJ.method("arg")}` calls into your objects
- **SSI-style includes** — `<!-- #include file="part.html" -->`
- **Pattern auto-assign** — `setPattern('LANG_')` maps constants/globals by prefix
- **pre_filter** — regex transform applied before parsing
- **Page caching** — `.ft` cache files with configurable TTL
- **Strict mode** — warns about unresolved `{VAR}`s instead of stripping them
- **Zero dependencies** — one file, no composer required

## Requirements

- PHP **7.4 – 8.4** (tested on 7.4; static-audited for 8.x)
- No extensions beyond core PHP

## Installation

Drop `class.FastTemplate.php` into your project:

```php
require '/path/to/class.FastTemplate.php';
$tpl = new FastTemplate('/path/to/templates');
```

## Template syntax

```html
<h1>{TITLE}</h1>                          <!-- variable -->
<p>{USER.name}</p>                        <!-- object method -->
<p>{USER.greet("bob")}</p>                <!-- object method w/ args -->

<!-- IFDEF: SHOW_EXTRA -->                <!-- if $SHOW_EXTRA is set/truthy -->
  <p>Extra: {EXTRA}</p>
<!-- ELSE -->
  <p>Nothing extra</p>
<!-- ENDIF -->

<!-- IFNDEF: HIDDEN -->                   <!-- if not defined -->
  <p>Visible</p>
<!-- ENDIF -->

<!-- #include file="header.html" -->      <!-- SSI-style include -->

<table>
<!-- BEGIN DYNAMIC BLOCK: row -->          <!-- repeated via .row appends -->
  <tr><td>{N}</td></tr>
<!-- END DYNAMIC BLOCK: row -->
</table>
```

## API quick reference

| Method | Purpose |
|---|---|
| `define($handle, $file)` / `define(array)` | map handles to template files |
| `define_dynamic($macro, $parent)` | register a dynamic block |
| `assign($key, $val)` / `assign(array)` | assign variables |
| `parse($out, $handle)` | parse template into `$out` |
| `parse($out, '.handle')` | append-parse (for loops) |
| `parse($out, array(...))` | compound parse |
| `fetch($out)` / `FastPrint($out)` / `FastWrite($out, $file)` | output |
| `parse_and_return($handle)` | parse + trimmed result |
| `strict()` / `no_strict()` | unresolved-var warnings |
| `pre_filter($pattern, $replace)` | pre-parse regex filter |
| `setPattern($prefix, $type)` | auto-assign constants (1) / globals (0) |
| `clear*()` | `clear`, `clear_parse`, `clear_tpl`, `clear_define`, `clear_assign`, `clear_dynamic`, `clear_all` |
| `USE_CACHE($f)`, `setCacheTime($s)`, `DELETE_CACHE()` | caching |

## New in 1.7.0

**PHP 7.4–8.4 compatibility**

- Removed `each()`, `ereg()`, `split()`, `mysql_escape_string()`,
  `get_magic_quotes_gpc()`, curly-brace offsets
- `__construct()` replaces the PHP4-style constructor (name alias kept)
- `#[AllowDynamicProperties]` for PHP 8.2+
- TypeError/ValueError guards (`count()` on strings, `preg_match_all` on
  NULL, `fopen('')`, `str_replace` on objects/NULL)

**Bug fixes**

- `DELETE_CACHE()` inverted loop, `emptyPattern()`, `set_root('')`,
  `self_script()`, `showDebugInfo()` unreachable branch
- `stripslashes()` removed from `getfast()` (corrupted `\`)
- `pre_filter()` is actually applied now
- `error()` gained `ERROR_ECHO` flag (echo vs `error_log`)

**Security**

- Method-name validation before `eval()` (template-driven RCE protection)
- `readfile()` instead of `include` for `.ft` cache files

**Performance** *(measured, PHP 7.4)*

- `strtr()`-map variable substitution replaces the per-var `str_replace`
  loop → **12× faster** end-to-end (44ms → 3.6ms on 600 vars / 370KB)
- IFDEF normalization, dynamic-strip and `parse_defined()` only run when
  `<!--` markers exist; per-line fast-path in `parse_defined()`
- **New flags:**
  - `$tpl->FAST_REPLACE = true` *(default)* — `strtr` engine; `false` =
    byte-identical legacy loop
  - `$tpl->FAST_BLOCK_SKIP = true` *(opt-in)* — skip block stages entirely
    on marker-free templates; **43×** vs legacy. Note: legacy trims every
    line and appends trailing newlines — `FAST_BLOCK_SKIP` preserves bytes
    exactly (fixes a latent `<pre>`/`<textarea>` whitespace bug)

**Known intentional divergence:** a value referencing an *earlier*-registered
variable (`assign('A','e'); assign('B','{A}')`) is now resolved; legacy dropped
it. Set `FAST_REPLACE = false` for exact legacy behavior.

## Testing

Self-contained suite, no PHPUnit needed:

```
php tests\run.php
```

53 tests covering parsing, dynamic blocks, IFDEF, object tags, includes,
flags parity, strict mode, `clear*`, error paths, `FastPrint`/`FastWrite`
and `setPattern`.

## License

General Artistic License (see file header). Original copyrights retained per
license stipulations.
