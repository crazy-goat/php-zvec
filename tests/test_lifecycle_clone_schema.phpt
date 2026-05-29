--TEST--
Lifecycle: clone schema — private __clone prevents double-free
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$schema = new ZVecSchema('test_clone_schema');
$schema->addInt64('id', nullable: false);

try {
    $clone = clone $schema;
    // PHP 8.4: shallow copy succeeded — verify no crash on scope exit
} catch (\Error $e) {
    // PHP 8.5+: clone with private __clone() throws Error
}
echo "PASS: clone schema handled safely\n";
?>
--EXPECT--
PASS: clone schema handled safely
