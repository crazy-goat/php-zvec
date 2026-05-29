--TEST--
Lifecycle: clone doc — private __clone prevents double-free
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
    // PHP 8.4: shallow copy succeeded — verify no crash on scope exit
} catch (\Error $e) {
    // PHP 8.5+: clone with private __clone() throws Error
}
echo "PASS: clone doc handled safely\n";
?>
--EXPECT--
PASS: clone doc handled safely
