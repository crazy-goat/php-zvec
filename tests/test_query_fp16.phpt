--TEST--
FP16 vector query: queryFp16(), queryById, filter, includeVector with half-precision vectors
--SKIPIF--
<?php
if (extension_loaded('zvec')) die('skip Native zvec extension loaded (use FFI)');
if (!extension_loaded('ffi')) die('skip FFI extension not available');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/fp16_q_' . uniqid();
try {
    $schema = new ZVecSchema('fp16_test');
    $schema->addVectorFp16('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $schema->addInt64('id', nullable: false);
    $schema->addString('cat', nullable: true);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('v');

    // Insert docs with FP16 vectors (hex values represent half-precision floats)
    // 0x3C00=1.0, 0x4000=2.0, 0x4200=3.0, 0x4400=4.0, 0x4800=8.0, 0x4C00=16.0
    $doc1 = new ZVecDoc('doc1');
    $doc1->setInt64('id', 1)->setString('cat', 'A');
    $doc1->setVectorFp16('v', [0x3C00, 0x4000, 0x4200, 0x4400]); // [1.0, 2.0, 3.0, 4.0]

    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)->setString('cat', 'B');
    $doc2->setVectorFp16('v', [0x3800, 0x3C00, 0x4000, 0x4200]); // [0.5, 1.0, 2.0, 3.0]

    $doc3 = new ZVecDoc('doc3');
    $doc3->setInt64('id', 3)->setString('cat', 'A');
    $doc3->setVectorFp16('v', [0x4800, 0x4C00, 0x3C00, 0x4000]); // [8.0, 16.0, 1.0, 2.0]

    $c->insert($doc1, $doc2, $doc3);
    $c->flush();
    $c->optimize();
    echo "Inserted 3 FP16 docs\n";

    // Helper: fetch PK
    $fetch = function($pk) use ($c) { return $c->fetch($pk)[0]; };

    // Test 1: queryFp16() — verify doc1 (identical vector) appears
    $results = $c->queryFp16('v', [0x3C00, 0x4000, 0x4200, 0x4400], topk: 3);
    assert(count($results) === 3, 'Expected 3 results');
    $pks = array_map(fn($d) => $d->getPk(), $results);
    assert(in_array('doc1', $pks), 'Expected doc1 in results');
    echo "queryFp16 returned results OK\n";

    // Test 2: queryFp16 with includeVector
    $results = $c->queryFp16('v', [0x3C00, 0x4000, 0x4200, 0x4400], topk: 1, includeVector: true);
    assert(count($results) === 1, 'Expected 1 result');
    $v = $results[0]->getVectorFp16('v');
    assert($v !== null, 'Expected FP16 vector with includeVector');
    echo "queryFp16 with includeVector OK\n";

    // Test 3: queryFp16 with filter
    $results = $c->queryFp16('v', [0x3C00, 0x4000, 0x4200, 0x4400], topk: 3, filter: "cat = 'A'");
    assert(count($results) === 2, 'Expected 2 results filtered by cat=A');
    foreach ($results as $r) {
        assert($r->getString('cat') === 'A', 'All results should have cat=A');
    }
    echo "queryFp16 with filter OK\n";

    // Test 4: queryById with FP16
    $results = $c->queryById('v', 'doc1', topk: 3);
    assert(count($results) >= 1, 'Expected at least 1 result');
    echo "queryById with FP16 OK\n";

    // Test 5: Fetch and verify FP16 vectors
    $d1 = $fetch('doc1');
    $v1 = $d1->getVectorFp16('v');
    assert($v1 !== null, 'Expected FP16 vector on fetched');
    assert(count($v1) === 4, 'Expected dimension 4');
    assert($v1[0] === 0x3C00, "Expected 0x3C00, got {$v1[0]}");
    echo "Fetched FP16 vectors OK\n";

    // Test 6: hasVector/vectorNames with FP16
    assert($d1->hasVector('v'), 'Expected hasVector true');
    $vecNames = $d1->vectorNames();
    assert(in_array('v', $vecNames), 'Expected v in vectorNames');
    echo "hasVector/vectorNames with FP16 OK\n";

    echo "ALL TESTS PASSED\n";
} finally {
    if (isset($c)) { try { $c->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 3 FP16 docs
queryFp16 returned results OK
queryFp16 with includeVector OK
queryFp16 with filter OK
queryById with FP16 OK
Fetched FP16 vectors OK
hasVector/vectorNames with FP16 OK
ALL TESTS PASSED