<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/column_drop_' . uniqid();

try {
    $schema = new ZVecSchema('drop_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addInt64('temp_val', nullable: true)
        ->addFloat('score', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert test docs with data
    $doc1 = new ZVecDoc('doc1');
    $doc1->setInt64('id', 1)
        ->setInt64('temp_val', 100)
        ->setFloat('score', 3.14)
        ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc1);
    
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)
        ->setInt64('temp_val', 200)
        ->setFloat('score', 6.28)
        ->setVectorFp32('v', [0.2, 0.3, 0.4, 0.5]);
    $c->insert($doc2);
    
    $c->optimize();

    // Test 1: Drop existing column
    $c->dropColumn('temp_val');
    $c->flush();
    
    $fetched = $c->fetch('doc1');
    assert(count($fetched) === 1, 'Expected 1 doc');
    
    // Verify schema no longer contains dropped column
    $schemaStr = $c->schema();
    assert(strpos($schemaStr, "'temp_val'") === false, 'Schema should not contain dropped temp_val');
    echo "Dropped 'temp_val' column OK (removed from schema)\n";

    // Test 2: Other columns still work
    assert($fetched[0]->getInt64('id') === 1, 'id column should still work');
    $s = $fetched[0]->getFloat('score');
    assert(abs($s - 3.14) < 0.001, 'score column should still work');
    echo "Other columns (id, score, v) still accessible after drop\n";

    // Test 3: Drop affects schema for all docs (column removed from schema)
    $schemaStr2 = $c->schema();
    assert(strpos($schemaStr2, "'temp_val'") === false, 'Schema should not contain temp_val after drop');
    echo "Drop affects schema consistently\n";

    // Test 4: Try drop non-existent column (should fail)
    try {
        $c->dropColumn('nonexistent');
        echo "FAIL: Should not be able to drop non-existent column\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "Correctly rejected dropping non-existent column\n";
    }

    // Test 5: Drop another column (score)
    $c->dropColumn('score');
    $c->flush();
    
    $schemaStr = $c->schema();
    assert(strpos($schemaStr, "'score'") === false, 'Schema should not contain dropped score');
    echo "Dropped 'score' column OK\n";

    // Test 6: Verify schema reflects drops
    $schemaStr = $c->schema();
    assert(strpos($schemaStr, "'temp_val'") === false, 'Schema should not contain dropped temp_val');
    assert(strpos($schemaStr, "'score'") === false, 'Schema should not contain dropped score');
    assert(strpos($schemaStr, "'id'") !== false, 'Schema should still contain id');
    assert(strpos($schemaStr, "'v'") !== false, 'Schema should still contain vector v');
    echo "Schema correctly reflects dropped columns\n";

    // Test 7: Can add new column with same name as dropped (reuse name)
    $c->addColumnInt64('temp_val', nullable: true, defaultExpr: '999');
    $c->flush();
    
    $fetched = $c->fetch('doc1');
    $newVal = $fetched[0]->getInt64('temp_val');
    assert($newVal === 999, "Re-added column should have default value, got $newVal");
    echo "Successfully re-added column with same name as dropped\n";

    // Test 8: Verify re-added column appears in schema
    $schemaStr = $c->schema();
    assert(strpos($schemaStr, "'temp_val'") !== false, 'Schema should contain re-added temp_val');
    echo "Re-added column appears in schema\n";

    $c->close();
    
    echo "PASS: All dropColumn() scenarios work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
