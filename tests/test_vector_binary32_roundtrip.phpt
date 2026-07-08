--TEST--
VECTOR_INT8 round-trip (variant): insert, fetch with 8-bit signed integer vectors (alternative pattern)
--SKIPIF--
<?php
if (extension_loaded('zvec')) die('skip Native zvec extension loaded (use FFI)');
if (!extension_loaded('ffi')) die('skip FFI extension not available');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/int8_b_' . uniqid();
try {
    $schema = new ZVecSchema('int8_test');
    $schema->addVectorInt8('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema->addInt64('id', nullable: false);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('v', metricType: ZVecSchema::METRIC_IP);

    // Insert docs with various INT8 vector patterns
    $docs = [
        ['doc1', 1, [0, 0, 0, 0]],
        ['doc2', 2, [1, 2, 3, 4]],
        ['doc3', 3, [4, 3, 2, 1]],
    ];

    foreach ($docs as [$pk, $id, $vec]) {
        $doc = new ZVecDoc($pk);
        $doc->setInt64('id', $id)->setVectorInt8('v', $vec);
        $c->insert($doc);
    }
    $c->flush();
    $c->optimize();
    echo "Inserted 3 INT8 docs\n";

    // Test 1: Fetch and verify each by PK
    $d1 = $c->fetch('doc1')[0];
    $v1 = $d1->getVectorInt8('v');
    assert($v1 === [0, 0, 0, 0], "Expected [0,0,0,0], got " . json_encode($v1));

    $d2 = $c->fetch('doc2')[0];
    $v2 = $d2->getVectorInt8('v');
    assert($v2 === [1, 2, 3, 4], "Expected [1,2,3,4]");

    $d3 = $c->fetch('doc3')[0];
    $v3 = $d3->getVectorInt8('v');
    assert($v3 === [4, 3, 2, 1], "Expected [4,3,2,1]");
    echo "Fetched INT8 vectors OK\n";

    // Test 2: hasVector on INT8
    assert($d1->hasVector('v'), 'Expected hasVector');
    assert(!$d1->hasVector('nonexistent'), 'Expected no hasVector');
    $vecNames = $d1->vectorNames();
    assert(in_array('v', $vecNames), 'Expected v in vectorNames');
    echo "hasVector/vectorNames OK\n";

    echo "ALL TESTS PASSED\n";
} finally {
    if (isset($c)) { try { $c->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 3 INT8 docs
Fetched INT8 vectors OK
hasVector/vectorNames OK
ALL TESTS PASSED