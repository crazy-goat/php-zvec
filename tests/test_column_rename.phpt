--TEST--
Column rename: renameColumn operation and error cases
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_column_rename_' . uniqid();

try {
    $schema = new ZVecSchema('rename_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addInt64('value', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert test doc with data
    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1)
        ->setInt64('value', 100)
        ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->optimize();

    // Test 1: Rename existing column
    $c->renameColumn('value', 'score');
    $c->flush();
    
    $fetched = $c->fetch('doc1');
    assert(count($fetched) === 1, 'Expected 1 doc');
    assert($fetched[0]->getInt64('score') === 100, 'Expected score=100 after rename');
    echo "Renamed 'value' -> 'score' OK\n";

    // Test 2: Verify schema reflects rename (old name not in schema)
    $schemaStr = $c->schema();
    assert(strpos($schemaStr, "'value'") === false, 'Schema should not contain old name "value"');
    assert(strpos($schemaStr, "'score'") !== false, 'Schema should contain new name "score"');
    echo "Schema correctly updated after rename\n";

    // Test 3: Rename again (score -> rating)
    $c->renameColumn('score', 'rating');
    $c->flush();
    
    $fetched = $c->fetch('doc1');
    assert($fetched[0]->getInt64('rating') === 100, 'Expected rating=100 after second rename');
    echo "Renamed 'score' -> 'rating' OK\n";

    // Test 4: Try rename non-existent column (should fail)
    try {
        $c->renameColumn('nonexistent', 'newname');
        echo "FAIL: Should not be able to rename non-existent column\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "Correctly rejected renaming non-existent column\n";
    }

    // Test 5: Try rename to existing name (should fail)
    try {
        $c->renameColumn('rating', 'id');  // 'id' already exists
        echo "FAIL: Should not be able to rename to existing name\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "Correctly rejected renaming to existing name\n";
    }

    // Test 6: Rename preserves data integrity across multiple docs
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)
        ->setInt64('rating', 200)  // Use the renamed column
        ->setVectorFp32('v', [0.2, 0.3, 0.4, 0.5]);
    $c->insert($doc2);
    $c->flush();

    // Add a new column and rename it
    $c->addColumnFloat('temp', nullable: true, defaultExpr: '1.0');
    $c->flush();
    
    $c->renameColumn('temp', 'temperature');
    $c->flush();
    
    $allDocs = $c->fetch('doc1', 'doc2');
    assert(count($allDocs) === 2, 'Expected 2 docs');
    
    foreach ($allDocs as $doc) {
        $temp = $doc->getFloat('temperature');
        assert(abs($temp - 1.0) < 0.001, 'All docs should have temperature≈1.0');
    }
    echo "Renamed column with default values works across all docs\n";

    // Test 7: Verify final schema
    $schemaStr = $c->schema();
    assert(strpos($schemaStr, "'rating'") !== false, 'Schema should contain "rating"');
    assert(strpos($schemaStr, "'score'") === false, 'Schema should not contain old name "score"');
    assert(strpos($schemaStr, "'temperature'") !== false, 'Schema should contain "temperature"');
    assert(strpos($schemaStr, "'temp'") === false, 'Schema should not contain old name "temp"');
    echo "Final schema verification OK\n";

    $c->close();
    
    echo "PASS: All renameColumn() scenarios work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Renamed 'value' -> 'score' OK
Schema correctly updated after rename
Renamed 'score' -> 'rating' OK
Correctly rejected renaming non-existent column
Correctly rejected renaming to existing name
Renamed column with default values works across all docs
Final schema verification OK
PASS: All renameColumn() scenarios work
