--TEST--
DOUBLE scalar round-trip: insert, fetch, and query with double-precision floats
--SKIPIF--
<?php
if (extension_loaded('zvec')) die('skip Native zvec extension loaded (use FFI)');
if (!extension_loaded('ffi')) die('skip FFI extension not available');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/double_' . uniqid();
try {
    $schema = new ZVecSchema('double_test');
    $schema->addInt64('id', nullable: false);
    $schema->addDouble('d', nullable: true);
    $schema->addVectorFp32('v', dimension: 2, metricType: ZVecSchema::METRIC_COSINE);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('v');

    $eps = 1e-10;

    // Insert docs with various DOUBLE values
    $docs = [
        ['doc1', 1, 0.0],
        ['doc2', 2, 3.14159265358979],
        ['doc3', 3, -2.5e100],
        ['doc4', 4, 1.23456789],
    ];

    foreach ($docs as [$pk, $id, $dval]) {
        $doc = new ZVecDoc($pk);
        $doc->setInt64('id', $id)->setDouble('d', $dval)->setVectorFp32('v', [(float)$id, (float)$id * 2]);
        $c->insert($doc);
    }

    $c->flush();
    $c->optimize();
    echo "Inserted 4 DOUBLE docs\n";

    // Helper: fetch doc by PK
    $f = function($pk) use ($c) { return $c->fetch($pk)[0]; };

    // Test 1: Fetch and verify each doc by PK
    $d1 = $f('doc1');
    assert(abs($d1->getDouble('d') - 0.0) < $eps, "Expected doc1 d=0.0");
    $d2 = $f('doc2');
    assert(abs($d2->getDouble('d') - 3.14159265358979) < $eps, "Expected doc2 d=3.14159");
    $d3 = $f('doc3');
    assert(abs($d3->getDouble('d') - (-2.5e100)) < 1e90, "Expected doc3 d=-2.5e100");
    $d4 = $f('doc4');
    assert(abs($d4->getDouble('d') - 1.23456789) < $eps, "Expected doc4 d=1.23456789");
    echo "Fetched DOUBLE values OK\n";

    // Test 2: getFloat returns null for DOUBLE field
    assert($d1->getFloat('d') === null, 'Expected null from getFloat on DOUBLE field');
    echo "getFloat returns null for DOUBLE field OK\n";

    // Test 3: Query and verify results contain correct DOUBLE values
    $results = $c->query('v', [1.0, 2.0], topk: 5);
    assert(count($results) >= 4, 'Expected at least 4 results');
    foreach ($results as $r) {
        if ($r->getPk() === 'doc2') {
            assert(abs($r->getDouble('d') - 3.14159265358979) < $eps, "Expected doc2 d=3.14159");
        }
    }
    echo "Query results contain correct DOUBLE values OK\n";

    // Test 4: hasField and fieldNames includes DOUBLE
    assert($d1->hasField('d'), 'Expected hasField true for d');
    $fieldNames = $d1->fieldNames();
    assert(in_array('d', $fieldNames), 'Expected d in fieldNames');
    echo "hasField/fieldNames with DOUBLE OK\n";

    // Test 5: Unset DOUBLE field returns null via getter
    $docNull = new ZVecDoc('null_test');
    $docNull->setInt64('id', 99)->setVectorFp32('v', [99.0, 199.0]);
    $c->insert($docNull);
    $c->flush();
    $dn = $c->fetch('null_test')[0];
    assert($dn->getDouble('d') === null, 'Expected null for unset DOUBLE field');
    echo "Unset DOUBLE field returns null OK\n";

    // Test 6: getDouble on non-existent field returns null
    assert($d1->getDouble('nonexistent') === null, 'Expected null for non-existent field');
    echo "getDouble on non-existent field returns null OK\n";

    echo "ALL TESTS PASSED\n";
} finally {
    if (isset($c)) { try { $c->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 4 DOUBLE docs
Fetched DOUBLE values OK
getFloat returns null for DOUBLE field OK
Query results contain correct DOUBLE values OK
hasField/fieldNames with DOUBLE OK
Unset DOUBLE field returns null OK
getDouble on non-existent field returns null OK
ALL TESTS PASSED