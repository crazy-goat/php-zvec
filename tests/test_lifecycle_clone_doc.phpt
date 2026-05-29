--TEST--
Lifecycle: clone doc throws Error — private __clone prevents double-free
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$doc = new ZVecDoc('test_clone_doc');
$doc->setInt64('id', 1);

try {
    $clone = clone $doc;
    echo "FAIL: clone should have thrown\n";
    exit(1);
} catch (\Error $e) {
    echo "PASS: clone doc throws " . $e->getMessage() . "\n";
}
?>
--EXPECT--
PASS: clone doc throws Call to private method ZVecDoc::__clone() from global scope
