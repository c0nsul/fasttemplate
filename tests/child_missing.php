<?php
// Child process: get_template() on a missing file must call error(...,1) -> exit().
// run.php asserts the marker is NEVER reached and the error text is printed.
require dirname ( __DIR__ ) . '/class.fasttemplate.php';
$ft = new FastTemplate ( __DIR__ );
$ft->define ( 'm', 'missing_nope.html' );
$ft->parse ( 'M', 'm' );
echo "SURVIVED\n"; // must never be reached
