--TEST--
Lifecycle: insert duplicate PK throws exception
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/lifecycle_dup_pk_' . uniqid();

try {
    $schema = new ZVecSchema('test_dup_pk');
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    $doc1 = new ZVecDoc('same_pk');
    $doc1->setInt64('id', 1);
    $doc1->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc1);

    $doc2 = new ZVecDoc('same_pk');
    $doc2->setInt64('id', 2);
    $doc2->setVectorFp32('v', [0.5, 0.6, 0.7, 0.8]);

    try {
        $c->insert($doc2);
        echo "FAIL: should have thrown\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "PASS: duplicate PK rejected (code={$e->getCode()})\n";
    }
    $c->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
PASS: duplicate PK rejected (code=%d)
