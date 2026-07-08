--TEST--
VECTOR_INT8 round-trip: insert, fetch with 8-bit signed integer vectors
--SKIPIF--
<?php
if (extension_loaded('zvec')) die('skip Native zvec extension loaded (use FFI)');
if (!extension_loaded('ffi')) die('skip FFI extension not available');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/int8_' . uniqid();
try {
    $schema = new ZVecSchema('int8_test');
    $schema->addVectorInt8('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema->addInt64('id', nullable: false);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('v', metricType: ZVecSchema::METRIC_IP);

    // Insert docs with INT8 vectors
    $docs = [
        ['doc1', 1, [1, 2, 3, 4]],
        ['doc2', 2, [1, 1, 1, 1]],
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

    // Fetch each doc by PK and verify
    $v1 = $c->fetch('doc1')[0]->getVectorInt8('v');
    assert($v1 !== null, 'Expected INT8 vector');
    assert($v1 === [1, 2, 3, 4], "Expected [1,2,3,4], got " . json_encode($v1));

    $v2 = $c->fetch('doc2')[0]->getVectorInt8('v');
    assert($v2 === [1, 1, 1, 1], "Expected [1,1,1,1]");

    $v3 = $c->fetch('doc3')[0]->getVectorInt8('v');
    assert($v3 === [4, 3, 2, 1], "Expected [4,3,2,1]");
    echo "Fetched INT8 vectors OK\n";

    // hasVector/vectorNames
    $d1 = $c->fetch('doc1')[0];
    assert($d1->hasVector('v'), 'Expected hasVector true');
    $vecNames = $d1->vectorNames();
    assert(in_array('v', $vecNames), 'Expected v in vectorNames');
    echo "hasVector/vectorNames with INT8 OK\n";

    echo "ALL TESTS PASSED\n";
} finally {
    if (isset($c)) { try { $c->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 3 INT8 docs
Fetched INT8 vectors OK
hasVector/vectorNames with INT8 OK
ALL TESTS PASSED