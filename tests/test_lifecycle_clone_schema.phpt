--TEST--
Lifecycle: clone schema throws Error — private __clone prevents double-free
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
    echo "FAIL: clone should have thrown\n";
    exit(1);
} catch (\Error $e) {
    echo "PASS: clone schema throws " . $e->getMessage() . "\n";
}
?>
--EXPECT--
PASS: clone schema throws Call to private method ZVecSchema::__clone() from global scope
