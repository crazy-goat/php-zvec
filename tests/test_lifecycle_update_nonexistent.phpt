--TEST--
Lifecycle: update non-existent document throws ZVecException
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/lifecycle_update_nonexist_' . uniqid();

try {
    $schema = new ZVecSchema('test_update_nonexist');
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    $doc = new ZVecDoc('nonexistent_pk');
    $doc->setInt64('id', 1);
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);

    try {
        $c->update($doc);
        echo "FAIL: should have thrown\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "PASS: update non-existent throws ZVecException (code={$e->getCode()})\n";
    }
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
PASS: update non-existent throws ZVecException (code=%d)
