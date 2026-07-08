--TEST--
UINT64 scalar round-trip: insert, fetch, and query with 64-bit unsigned integers
--SKIPIF--
<?php
if (extension_loaded('zvec')) die('skip Native zvec extension loaded (use FFI)');
if (!extension_loaded('ffi')) die('skip FFI extension not available');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/uint64_' . uniqid();
try {
    $schema = new ZVecSchema('uint64_test');
    $schema->addInt64('id', nullable: false);
    $schema->addUint64('u64', nullable: true);
    $schema->addVectorFp32('v', dimension: 2, metricType: ZVecSchema::METRIC_COSINE);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('v');

    // Insert docs with various UINT64 values
    $docs = [
        ['doc1', 1, 0],
        ['doc2', 2, 100],
        ['doc3', 3, 9999999999],
        ['doc4', 4, PHP_INT_MAX],
    ];

    foreach ($docs as [$pk, $id, $u64val]) {
        $doc = new ZVecDoc($pk);
        $doc->setInt64('id', $id)->setUint64('u64', $u64val)->setVectorFp32('v', [(float)$id, (float)$id * 2]);
        $c->insert($doc);
    }

    $c->flush();
    $c->optimize();
    echo "Inserted 4 UINT64 docs\n";

    // Helper: fetch doc by PK
    $f = function($pk) use ($c) { return $c->fetch($pk)[0]; };

    // Test 1: Fetch and verify each doc by PK
    $d1 = $f('doc1');
    assert($d1->getUint64('u64') === 0, "Expected doc1 u64=0, got " . var_export($d1->getUint64('u64'), true));
    $d2 = $f('doc2');
    assert($d2->getUint64('u64') === 100, "Expected doc2 u64=100");
    $d3 = $f('doc3');
    assert($d3->getUint64('u64') === 9999999999, "Expected doc3 u64=9999999999");
    $d4 = $f('doc4');
    assert($d4->getUint64('u64') === PHP_INT_MAX, "Expected doc4 u64=PHP_INT_MAX");
    echo "Fetched UINT64 values OK\n";

    // Test 2: getInt64 returns null for UINT64 field
    assert($d1->getInt64('u64') === null, 'Expected null from getInt64 on UINT64 field');
    echo "getInt64 returns null for UINT64 field OK\n";

    // Test 3: Query and verify results contain correct UINT64 values
    $results = $c->query('v', [1.0, 2.0], topk: 5);
    assert(count($results) >= 4, 'Expected at least 4 results');
    foreach ($results as $r) {
        if ($r->getPk() === 'doc1') {
            assert($r->getUint64('u64') === 0, "Expected doc1 u64=0");
        }
        if ($r->getPk() === 'doc4') {
            assert($r->getUint64('u64') === PHP_INT_MAX, "Expected doc4 u64=PHP_INT_MAX");
        }
    }
    echo "Query results contain correct UINT64 values OK\n";

    // Test 4: hasField and fieldNames includes UINT64
    assert($d1->hasField('u64'), 'Expected hasField true for u64');
    $fieldNames = $d1->fieldNames();
    assert(in_array('u64', $fieldNames), 'Expected u64 in fieldNames');
    echo "hasField/fieldNames with UINT64 OK\n";

    // Test 5: Unset UINT64 field returns null via getter
    $docNull = new ZVecDoc('null_test');
    $docNull->setInt64('id', 99)->setVectorFp32('v', [99.0, 199.0]);
    $c->insert($docNull);
    $c->flush();
    $dn = $c->fetch('null_test')[0];
    assert($dn->getUint64('u64') === null, 'Expected null for unset UINT64 field');
    echo "Unset UINT64 field returns null OK\n";

    echo "ALL TESTS PASSED\n";
} finally {
    if (isset($c)) { try { $c->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 4 UINT64 docs
Fetched UINT64 values OK
getInt64 returns null for UINT64 field OK
Query results contain correct UINT64 values OK
hasField/fieldNames with UINT64 OK
Unset UINT64 field returns null OK
ALL TESTS PASSED