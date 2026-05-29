--TEST--
Lifecycle: destroy after close works — reopen then destroy
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/lifecycle_destroy_after_close_' . uniqid();

try {
    $schema = new ZVecSchema('test_destroy_after_close');
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->close();
    $c->destroy();  // destroy after close should work
    echo "PASS: close then destroy works\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
PASS: close then destroy works
