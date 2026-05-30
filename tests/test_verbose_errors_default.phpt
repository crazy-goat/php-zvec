--TEST--
Verbose errors: default (disabled) strips file/line/function from exceptions
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip verboseErrors is FFI-only, not available with native zvec extension'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

try {
    $c = ZVec::open('/tmp/nonexistent_' . uniqid());
    echo "FAIL: Should have thrown exception\n";
    exit(1);
} catch (ZVecException $e) {
    $file = $e->getErrorFile();
    $line = $e->getErrorLine();
    $func = $e->getErrorFunction();

    if ($file === null && $line === null && $func === null) {
        echo "PASS: verboseErrors=false strips file/line/function\n";
    } else {
        echo "FAIL: Expected null details, got file=$file line=$line func=$func\n";
        exit(1);
    }
}
?>
--EXPECT--
PASS: verboseErrors=false strips file/line/function
