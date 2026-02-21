--TEST--
Alter column: rename, change data type, nullable flag
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/alter_column_' . uniqid();

$schema = new ZVecSchema('alter_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addInt64('value', nullable: true)
    ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);

// Insert test doc
$doc = new ZVecDoc('doc1');
$doc->setInt64('id', 1)
    ->setInt64('value', 100)
    ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
$c->insert($doc);
$c->optimize();

// Test 1: Rename column using alterColumn
$c->alterColumn('value', newName: 'score');
$fetched = $c->fetch('doc1');
assert(count($fetched) === 1, 'Expected 1 doc');
assert($fetched[0]->getInt64('score') === 100, 'Expected score=100 after rename');
echo "Renamed 'value' -> 'score' OK\n";

// Test 2: Change data type (INT64 -> FLOAT)
$c->alterColumn('score', newDataType: ZVec::TYPE_FLOAT, nullable: true);
$fetched = $c->fetch('doc1');
assert(count($fetched) === 1, 'Expected 1 doc');
$score = $fetched[0]->getFloat('score');
assert(abs($score - 100.0) < 0.001, "Expected score≈100.0 after type change, got $score");
echo "Changed 'score' type: INT64 -> FLOAT OK (value=$score)\n";

// Test 3: Change type first (keep nullable), then rename
$c->alterColumn('score', newDataType: ZVec::TYPE_DOUBLE, nullable: true);
$c->alterColumn('score', newName: 'rating');
$fetched = $c->fetch('doc1');
assert(count($fetched) === 1, 'Expected 1 doc');
$rating = $fetched[0]->getDouble('rating');
assert(abs($rating - 100.0) < 0.001, "Expected rating≈100.0, got $rating");
echo "Changed type (FLOAT -> DOUBLE) + renamed 'score' -> 'rating' OK\n";

// Test 4: Test all scalar numeric types in sequence
$c->dropColumn('rating');
$c->addColumnInt64('test_val', nullable: true, defaultExpr: '42');
$fetched = $c->fetch('doc1');
assert($fetched[0]->getInt64('test_val') === 42, 'Expected test_val=42');
echo "Added test_val (INT64=42) OK\n";

// INT64 -> INT32
$c->alterColumn('test_val', newDataType: ZVec::TYPE_INT32, nullable: true);
$schemaStr = $c->schema();
assert(strpos($schemaStr, "INT32") !== false || strpos($schemaStr, "data_type:4") !== false, 'Schema should show INT32');
echo "Changed type: INT64 -> INT32 OK\n";

// INT32 -> UINT32
$c->alterColumn('test_val', newDataType: ZVec::TYPE_UINT32, nullable: true);
$schemaStr = $c->schema();
assert(strpos($schemaStr, "UINT32") !== false || strpos($schemaStr, "data_type:6") !== false, 'Schema should show UINT32');
echo "Changed type: INT32 -> UINT32 OK\n";

// UINT32 -> UINT64
$c->alterColumn('test_val', newDataType: ZVec::TYPE_UINT64, nullable: true);
$schemaStr = $c->schema();
assert(strpos($schemaStr, "UINT64") !== false || strpos($schemaStr, "data_type:7") !== false, 'Schema should show UINT64');
echo "Changed type: UINT32 -> UINT64 OK\n";

// UINT64 -> DOUBLE
$c->alterColumn('test_val', newDataType: ZVec::TYPE_DOUBLE, nullable: true);
$fetched = $c->fetch('doc1');
$val = $fetched[0]->getDouble('test_val');
assert(abs($val - 42.0) < 0.001, "Expected test_val≈42.0, got $val");
echo "Changed type: UINT64 -> DOUBLE OK (value=$val)\n";

$c->dropColumn('test_val');

// Final verification - check schema is clean
$schemaStr = $c->schema();
assert(strpos($schemaStr, "test_val") === false, 'Schema should not contain dropped test_val');
echo "Final schema verification OK\n";

$c->close();
exec("rm -rf " . escapeshellarg($path));

echo "PASS: All alterColumn() scenarios work\n";
?>
--EXPECT--
Renamed 'value' -> 'score' OK
Changed 'score' type: INT64 -> FLOAT OK (value=100)
Changed type (FLOAT -> DOUBLE) + renamed 'score' -> 'rating' OK
Added test_val (INT64=42) OK
Changed type: INT64 -> INT32 OK
Changed type: INT32 -> UINT32 OK
Changed type: UINT32 -> UINT64 OK
Changed type: UINT64 -> DOUBLE OK (value=42)
Final schema verification OK
PASS: All alterColumn() scenarios work
