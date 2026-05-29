--TEST--
Lifecycle: destroy then __destruct — no double-free crash
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/lifecycle_destroy_destruct_' . uniqid();

try {
    $schema = new ZVecSchema('test_destroy');
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1);
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->optimize();

    $c->destroy();
    echo "PASS: destroy + destruct no crash\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
PASS: destroy + destruct no crash
