--TEST--
Lifecycle: method on destroyed collection throws ZVecException
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/lifecycle_method_on_destroyed_' . uniqid();

try {
    $schema = new ZVecSchema('test_destroyed_method');
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1);
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->destroy();

    try {
        $c->insert($doc);
        echo "FAIL: should have thrown\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "PASS: insert on destroyed throws ZVecException\n";
    }

    try {
        $c->query('v', [0.1, 0.2, 0.3, 0.4]);
        echo "FAIL: should have thrown\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "PASS: query on destroyed throws ZVecException\n";
    }

    try {
        $c->stats();
        echo "FAIL: should have thrown\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "PASS: stats on destroyed throws ZVecException\n";
    }
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
PASS: insert on destroyed throws ZVecException
PASS: query on destroyed throws ZVecException
PASS: stats on destroyed throws ZVecException
