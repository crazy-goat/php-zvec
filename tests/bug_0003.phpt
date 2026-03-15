--TEST--
Bug 0003: Calling methods on destroyed collection throws exception instead of segfault
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/destroy_segfault_' . uniqid();

try {
    $schema = new ZVecSchema('segfault_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert a doc
    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1)
        ->setVectorFp32('embedding', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);

    // Destroy the collection
    $c->destroy();

    // Calling method on destroyed collection should throw exception (not segfault)
    try {
        $stats = $c->stats();
        echo "FAIL: Should have thrown exception on destroyed collection\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "PASS: Got exception instead of segfault: " . substr($e->getMessage(), 0, 50) . "\n";
    }
} finally {
    if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
PASS: Got exception instead of segfault: %s
