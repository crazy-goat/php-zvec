--TEST--
Lifecycle: open non-existent path throws ZVecException
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/lifecycle_nonexistent_' . uniqid();

try {
    try {
        ZVec::open($path);
        echo "FAIL: should have thrown\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "PASS: open non-existent throws ZVecException (code={$e->getCode()})\n";
    }
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
PASS: open non-existent throws ZVecException (code=%d)
