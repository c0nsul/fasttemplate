<?php
/**
 * Self-contained test runner for FastTemplate (no external deps).
 * Usage: php tests\run.php
 * Exit code: 0 = all green, 1 = failures.
 */
error_reporting ( E_ALL & ~ E_DEPRECATED & ~ E_NOTICE );

$ROOT = dirname ( __DIR__ );
$FIX = __DIR__ . '/fixtures';
require $ROOT . '/class.fasttemplate.php';

$PASS = 0;
$FAIL = 0;

function t_ok($cond, $name) {
	global $PASS, $FAIL;
	if ($cond) {
		$PASS ++;
		echo "  PASS  $name\n";
	} else {
		$FAIL ++;
		echo "  FAIL  $name\n";
	}
}
function t_eqstr($exp, $act, $name) {
	global $PASS, $FAIL;
	if ($exp === $act) {
		$PASS ++;
		echo "  PASS  $name\n";
	} else {
		$FAIL ++;
		echo "  FAIL  $name\n        expected: " . var_export ( $exp, TRUE ) . "\n        actual:   " . var_export ( $act, TRUE ) . "\n";
	}
}
function t_has($needle, $hay, $name) {
	t_ok ( strpos ( ( string ) $hay, $needle ) !== FALSE, "$name [contains '$needle']" );
}
function t_not($needle, $hay, $name) {
	t_ok ( strpos ( ( string ) $hay, $needle ) === FALSE, "$name [not contains '$needle']" );
}
function ft($root) {
	$t = new FastTemplate ( $root );
	$t->ERROR_ECHO = FALSE; // keep output clean; errors still recorded in $t->ERROR
	return $t;
}
function section($s) {
	echo "\n== $s ==\n";
}

class FTD {
	public function name() {
		return 'objname';
	}
	public function greet($who = "x") {
		return "hi-" . $who;
	}
}

// ------------------------------------------------------------------
section ( 'core: define/assign/parse/fetch' );

$t = ft ( $FIX );
$t->define ( 'main', 'main.html' );
$t->assign ( array ('TITLE' => 'My Page','NAME' => 'World','AGE' => 42,'EXTRA' => 'visible' ) );
$t->parse ( 'MAIN', 'main' );
$out = $t->fetch ( 'MAIN' );
t_has ( '<h1>My Page</h1>', $out, 'var substitution {TITLE}' );
t_has ( 'Name: World, Age: 42', $out, 'var substitution NAME/AGE' );

section ( 'IFDEF / IFNDEF / ELSE / ENDIF' );

$t->assign ( 'SHOW_EXTRA', 1 );
$t->parse ( 'MAIN', 'main' );
$out = $t->fetch ( 'MAIN' );
t_has ( 'Extra: visible', $out, 'IFDEF true branch rendered' );
t_not ( 'No extra', $out, 'ELSE branch removed' );
t_has ( 'Not hidden', $out, 'IFNDEF (missing var) rendered' );
t_not ( 'IFDEF', $out, 'markers stripped' );
t_not ( 'ENDIF', $out, 'ENDIF stripped' );

section ( 'dynamic blocks + append .row' );

$t->define ( 'rows', 'rows.html' );
$t->define_dynamic ( 'row', 'rows' );
for($i = 1; $i <= 3; $i ++) {
	$t->assign ( 'N', $i );
	$t->parse ( 'ROWS', '.row' );
}
$rows = $t->fetch ( 'ROWS' );
t_has ( '#1', $rows, 'dynamic row 1' );
t_has ( '#3', $rows, 'dynamic row 3' );
$t->parse ( 'ROWS', 'rows' );
$parent = $t->fetch ( 'ROWS' );
t_has ( 'Total: World', $parent, 'parent parsed' );
t_not ( 'BEGIN DYNAMIC', $parent, 'dynamic block stripped from parent' );

section ( 'compound parse (array)' );

$t->define ( array ('inner' => 'inner.html','compound' => 'compound.html' ) );
$t->parse ( 'OUT', array ('inner','compound' ) );
$out = $t->fetch ( 'OUT' );
t_has ( '<wrap>', $out, 'compound outer' );
t_has ( '<i>World</i>', $out, 'compound inner resolved' );

section ( 'object tag' );

$t->define ( 'obj', 'obj.html' );
$t->assign ( 'OBJ', new FTD );
$t->parse ( 'O', 'obj' );
$out = $t->fetch ( 'O' );
t_has ( 'objname', $out, '{OBJ.name} -> method call' );
t_has ( 'hi-bob', $out, '{OBJ.greet("bob")} -> method with param' );

section ( 'SSI include' );

$t->define ( 'inc', 'inc.html' );
$t->parse ( 'I', 'inc' );
$out = $t->fetch ( 'I' );
t_has ( 'included:World', $out, '#include file substituted + vars parsed' );
t_has ( 'A', $out, 'prefix preserved' );

section ( 'transitive substitution' );

$t->define ( 'nest', 'nest.html' );
$t->assign ( array ('FWD' => 'FWD->{NAME2}','NAME2' => 'late' ) );
$t->parse ( 'N', 'nest' );
$out = $t->fetch ( 'N' );
t_has ( 'FWD->late', $out, 'forward-ref resolved (both engines)' );

section ( 'documented divergence: backward-ref' );

// assign order: EARLY first, BWD second -> BWD value references EARLIER var.
// legacy str_replace loop drops it; strtr+loop resolves it.
$t2 = ft ( $FIX );
$t2->define ( 'bwd', 'bwd.html' );
$t2->FAST_REPLACE = TRUE;
$t2->assign ( array ('EARLY' => 'e','BWD' => 'BWD->{EARLY}' ) );
$t2->parse ( 'B', 'bwd' );
t_has ( 'BWD->eY', $t2->fetch ( 'B' ), 'FAST_REPLACE=TRUE resolves backward-ref' );

$t3 = ft ( $FIX );
$t3->define ( 'bwd', 'bwd.html' );
$t3->FAST_REPLACE = FALSE;
$t3->assign ( array ('EARLY' => 'e','BWD' => 'BWD->{EARLY}' ) );
$t3->parse ( 'B', 'bwd' );
t_has ( 'BWD->Y', $t3->fetch ( 'B' ), 'FAST_REPLACE=FALSE legacy drops it' );
t_not ( 'BWD->eY', $t3->fetch ( 'B' ), 'legacy does NOT resolve' );

section ( 'FAST_REPLACE=FALSE parity (byte-identical)' );

foreach ( array ('main','rows','obj','inc','pre' ) as $fx ) {
	$a = ft ( $FIX );
	$a->define ( $fx, $fx . '.html' );
	if ($fx === 'rows') {
		$a->define_dynamic ( 'row', 'rows' );
	}
	$a->assign ( array ('NAME' => 'World','A' => 'e','N' => 7,'OBJ' => new FTD,'SHOW_EXTRA' => 1,'EXTRA' => 'v' ) );
	$a->parse ( 'R', $fx );
	$fast = $a->fetch ( 'R' );

	$b = ft ( $FIX );
	$b->define ( $fx, $fx . '.html' );
	if ($fx === 'rows') {
		$b->define_dynamic ( 'row', 'rows' );
	}
	$b->FAST_REPLACE = FALSE;
	$b->assign ( array ('NAME' => 'World','A' => 'e','N' => 7,'OBJ' => new FTD,'SHOW_EXTRA' => 1,'EXTRA' => 'v' ) );
	$b->parse ( 'R', $fx );
	$leg = $b->fetch ( 'R' );

	t_eqstr ( $leg, $fast, "fr=0 vs fr=1 identical: $fx" );
}

section ( 'FAST_BLOCK_SKIP whitespace semantics' );

// legacy (bs=0): every line trimmed + trailing "\n" added per stage
$d = ft ( $FIX );
$d->define ( 'basic', 'basic.html' );
$d->assign ( 'A', 'e' );
$d->parse ( 'B', 'basic' );
t_eqstr ( "XeY\n\n", $d->fetch ( 'B' ), 'legacy: +2 trailing newlines (quirk pinned)' );

$e = ft ( $FIX );
$e->define ( 'basic', 'basic.html' );
$e->FAST_BLOCK_SKIP = TRUE;
$e->assign ( 'A', 'e' );
$e->parse ( 'B', 'basic' );
t_eqstr ( "XeY", $e->fetch ( 'B' ), 'bs=1: byte-exact input preserved' );

// <pre> indentation: legacy trims, bs=1 preserves
$f = ft ( $FIX );
$f->define ( 'pre', 'pre.html' );
$f->assign ( 'NAME', 'World' );
$f->parse ( 'P', 'pre' );
t_not ( '        indented', $f->fetch ( 'P' ), 'legacy trims indentation' );

$g = ft ( $FIX );
$g->define ( 'pre', 'pre.html' );
$g->FAST_BLOCK_SKIP = TRUE;
$g->assign ( 'NAME', 'World' );
$g->parse ( 'P', 'pre' );
t_has ( '        indented', $g->fetch ( 'P' ), 'bs=1 preserves indentation' );

section ( 'cycle guard' );

$h = ft ( $FIX );
$h->define ( 'bwd', 'bwd.html' );
$h->assign ( array ('BWD' => '{BWD2}','BWD2' => '{BWD}' ) ); // mutual cycle
$h->parse ( 'B', 'bwd' );
$out = $h->fetch ( 'B' );
t_not ( '{', $out, 'cycle resolved (residue cleaned, no hang)' );

section ( 'strict mode' );

$s = ft ( $FIX );
$s->define ( 'unk', 'unk.html' );
$s->strict ();
$s->STRICT_DEBUG = TRUE;
$s->parse ( 'U', 'unk' );
$out = $s->fetch ( 'U' );
t_has ( '{UNKNOWN_VAR}', $out, 'strict keeps unresolved var' );
$found = FALSE;
foreach ( $s->WARNINGS as $w ) {
	if (strpos ( $w, 'UNKNOWN_VAR' ) !== FALSE) {
		$found = TRUE;
	}
}
t_ok ( $found, 'STRICT_DEBUG records warning' );
$s->no_strict ();

section ( 'pre_filter' );

$pf = ft ( $FIX );
$pf->define ( 'pf', 'pf.html' );
$pf->assign ( 'NAME', 'World' );
$pf->pre_filter ( '/QTT/', 'FILTERED' );
$pf->parse ( 'PF', 'pf' );
t_has ( 'FILTERED World', $pf->fetch ( 'PF' ), 'pre_filter applied' );

section ( 'error handling' );

$e1 = ft ( $FIX );
$e1->ERROR_ECHO = TRUE;
ob_start ();
$e1->getfast ( 'NEVER_PARSED' );
$errOut = ob_get_clean ();
t_has ( 'Nothing parsed', $errOut, 'ERROR_ECHO=TRUE prints error' );

$e2 = ft ( $FIX );
$e2->ERROR_ECHO = FALSE;
ob_start ();
$e2->getfast ( 'NEVER_PARSED' );
$errOut2 = ob_get_clean ();
t_eqstr ( '', $errOut2, 'ERROR_ECHO=FALSE silent' );
t_has ( 'Nothing parsed', $e2->ERROR, 'error message recorded in ->ERROR' );

section ( 'clear / clear_parse / clear_define / clear_dynamic / clear_all' );

$c = ft ( $FIX );
$c->define ( 'basic', 'basic.html' );
$c->assign ( 'A', 'e' );
$c->parse ( 'C', 'basic' );
t_ok ( isset ( $c->C ), 'parsed var exists before clear' );
$c->clear ( 'C' );
t_ok ( ! isset ( $c->C ), 'clear(\'C\') removes it' );

$c->parse ( 'C', 'basic' );
$c->clear_parse ( 'C' );
t_ok ( ! isset ( $c->C ), 'clear_parse alias works' );

$c->clear_define ( 'basic' );
t_ok ( ! isset ( $c->FILELIST ['basic'] ), 'clear_define removes FILELIST entry' );

$c2 = ft ( $FIX );
$c2->define ( 'rows', 'rows.html' );
$c2->define_dynamic ( 'row', 'rows' );
t_ok ( isset ( $c2->DYNAMIC ['row'] ), 'DYNAMIC set' );
$rc = $c2->clear_dynamic ( 'row' );
// clear_dynamic strips the block from the LOADED parent template,
// it does not remove the DYNAMIC registry entry
t_ok ( $rc === TRUE, 'clear_dynamic returns TRUE' );
t_not ( 'BEGIN DYNAMIC BLOCK: row', $c2->rows, 'block stripped from loaded parent' );

$c3 = ft ( $FIX );
$c3->define ( 'basic', 'basic.html' );
$c3->assign ( 'A', 'e' );
$c3->parse ( 'C', 'basic' );
$c3->clear_all ();
t_ok ( empty ( $c3->PARSEVARS ), 'clear_all empties PARSEVARS' );
t_ok ( empty ( $c3->FILELIST ), 'clear_all empties FILELIST' );

section ( 'FastPrint / FastWrite' );

$p = ft ( $FIX );
$p->define ( 'basic', 'basic.html' );
$p->assign ( 'A', 'e' );
$p->parse ( 'FP', 'basic' );

$ret = $p->FastPrint ( 'FP', TRUE );
t_has ( 'XeY', $ret, 'FastPrint return mode' );

ob_start ();
$p->FastPrint ( 'FP' );
$echoed = ob_get_clean ();
t_has ( 'XeY', $echoed, 'FastPrint echo mode' );

$tmpFile = tempnam ( sys_get_temp_dir (), 'ft_' );
$p->FastWrite ( 'FP', $tmpFile );
$written = file_get_contents ( $tmpFile );
t_has ( 'XeY', $written, 'FastWrite writes output file' );
unlink ( $tmpFile );

section ( 'setPattern (vars + defines)' );

$GLOBALS ['FTP_TEST'] = 'gv';
define ( 'FTC_ONE', 'c1' );

$sp = ft ( $FIX );
$sp->define ( 'pat', 'pat.html' );
$sp->setPattern ( 'FTP_', 0 ); // variables
$sp->setPattern ( 'FTC_', 1 ); // defines
$sp->parse ( 'P', 'pat' );
$out = $sp->fetch ( 'P' );
t_has ( 'gv', $out, 'setPattern vars auto-assigned' );
t_has ( 'c1', $out, 'setPattern defines auto-assigned' );

section ( 'parse_and_return' );

$pr = ft ( $FIX );
$pr->define ( 'basic', 'basic.html' );
$pr->assign ( 'A', 'e' );
t_eqstr ( 'XeY', $pr->parse_and_return ( 'basic' ), 'parse_and_return trims output' );

section ( 'get_template failure (child process, die=1)' );

$childOut = shell_exec ( 'php "' . __DIR__ . DIRECTORY_SEPARATOR . 'child_missing.php" 2>&1' );
t_has ( 'file not found', ( string ) $childOut, 'missing file error printed' );
t_not ( 'SURVIVED', ( string ) $childOut, 'process halted (exit)' );

// ------------------------------------------------------------------
echo "\n========================================\n";
echo "PASSED: $PASS   FAILED: $FAIL\n";
exit ( $FAIL > 0 ? 1 : 0 );
