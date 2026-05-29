--TEST--
Lifecycle: close twice is idempotent — no error on second call
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/lifecycle_close_twice_' . uniqid();

try {
    $schema = new ZVecSchema('test_close_twice');
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->close();
    $c->close();  // Should be idempotent
    echo "PASS: close twice idempotent\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
PASS: close twice idempotent
