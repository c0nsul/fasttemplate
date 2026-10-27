# FastTemplate test suite

Self-contained, no external dependencies (no PHPUnit / composer required).

## Run

```
php tests\run.php
```

Exit code: `0` = all green, `1` = failures.

## Layout

- `run.php` — runner with assert helpers (`t_ok`, `t_eqstr`, `t_has`, `t_not`)
- `child_missing.php` — subprocess test for `get_template()` `die=1` path
  (cannot run in-process: `error(...,1)` calls `exit()`)
- `fixtures/*.html` — template fixtures

## Coverage

- **Core**: `define`, `assign`, `parse`, `fetch`, `parse_and_return`
- **Blocks**: IFDEF/IFNDEF/ELSE/ENDIF, dynamic blocks, `.row` append, compound parse
- **Tags**: object-tag `{Obj.method}`, `{Obj.method("arg")}`, SSI `<!-- #include -->`
- **Substitution**: forward-ref transitivity, backward-ref divergence
  (`FAST_REPLACE=TRUE` resolves, `FALSE` drops — documented 1.7.0 change)
- **Flags**: `FAST_REPLACE=FALSE` byte-identity, `FAST_BLOCK_SKIP` whitespace semantics
- **Edge**: strict/STRICT_DEBUG, `pre_filter`, `error()`/`ERROR_ECHO`,
  `clear*` family, `FastPrint`, `FastWrite`, `setPattern`

## Notes

- `FAST_BLOCK_SKIP=FALSE` (default) preserves the legacy quirk: every line is
  `trim()`-ed and +2 trailing newlines are appended per `parse_template()`.
  Tests pin this behaviour explicitly.
