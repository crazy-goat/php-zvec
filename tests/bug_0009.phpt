--TEST--
BUG-009: alterColumn() must require explicit nullable when changing data type
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/bug_0009_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addInt64('value', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1)
        ->setInt64('value', 42)
        ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->optimize();

    // Test 1: alterColumn with newDataType but no nullable — must throw
    try {
        $c->alterColumn('value', newDataType: ZVec::TYPE_FLOAT);
        echo "FAIL: should have thrown ZVecException\n";
    } catch (ZVecException $e) {
        $msg = $e->getMessage();
        assert(str_contains($msg, 'nullable must be explicitly specified'), "Expected clear error message, got: $msg");
        echo "PASS: alterColumn(newDataType) without nullable throws ZVecException\n";
    }

    // Test 2: alterColumn with newDataType + nullable: true — should work
    $c->alterColumn('value', newDataType: ZVec::TYPE_FLOAT, nullable: true);
    $fetched = $c->fetch('doc1');
    $score = $fetched[0]->getFloat('value');
    assert(abs($score - 42.0) < 0.001, "Expected value≈42.0, got $score");
    echo "PASS: alterColumn(newDataType, nullable: true) works\n";

    // Test 3: alterColumn with newDataType + nullable: false — C++ rejects true→false
    try {
        $c->alterColumn('value', newDataType: ZVec::TYPE_DOUBLE, nullable: false);
        echo "FAIL: nullable true→false should be rejected\n";
    } catch (ZVecException $e) {
        echo "PASS: alterColumn(newDataType, nullable: false) correctly rejected true→false\n";
    }

    // Test 4: rename only (no newDataType) — nullable not required
    $c->alterColumn('value', newName: 'score');
    $fetched = $c->fetch('doc1');
    $score = $fetched[0]->getFloat('score');
    assert(abs($score - 42.0) < 0.001, "Expected score≈42.0, got $score");
    echo "PASS: rename-only without nullable works\n";

    // Test 5: nullable-only change without newDataType — C++ rejects (requires new schema)
    try {
        $c->alterColumn('score', nullable: true);
        echo "PASS: nullable-only change works\n";
    } catch (ZVecException $e) {
        echo "PASS: nullable-only rejected (C++ requires newDataType): " . $e->getMessage() . "\n";
    }

    // Test 6: Verify column still has correct data after all operations
    $fetched = $c->fetch('doc1');
    assert(abs($fetched[0]->getFloat('score') - 42.0) < 0.001, 'Expected score≈42.0 unchanged');
    echo "PASS: data integrity verified after all operations\n";

    $c->close();
    echo "ALL TESTS PASSED\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
PASS: alterColumn(newDataType) without nullable throws ZVecException
PASS: alterColumn(newDataType, nullable: true) works
PASS: alterColumn(newDataType, nullable: false) correctly rejected true→false
PASS: rename-only without nullable works
PASS: nullable-only rejected (C++ requires newDataType): New column schema is null
PASS: data integrity verified after all operations
ALL TESTS PASSED
