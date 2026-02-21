--TEST--
Column add: addColumnInt64, addColumnFloat, addColumnDouble with defaults
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_column_add_' . uniqid();

try {
    $schema = new ZVecSchema('add_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert initial docs before adding columns
    $doc1 = new ZVecDoc('doc1');
    $doc1->setInt64('id', 1)
        ->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc1);
    
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)
        ->setVectorFp32('v', [0.2, 0.3, 0.4, 0.5]);
    $c->insert($doc2);
    
    $c->optimize();

    // Test 1: Add INT64 column with default value
    $c->addColumnInt64('counter', nullable: true, defaultExpr: '100');
    $c->flush();
    
    $fetched = $c->fetch('doc1');
    assert(count($fetched) === 1, 'Expected 1 doc');
    assert($fetched[0]->getInt64('counter') === 100, 'Expected counter=100 (default)');
    echo "Added INT64 column 'counter' with default=100 OK\n";

    // Test 2: Add FLOAT column with default value
    $c->addColumnFloat('score', nullable: true, defaultExpr: '3.14');
    $c->flush();
    
    $fetched = $c->fetch('doc2');
    assert(count($fetched) === 1, 'Expected 1 doc');
    $score = $fetched[0]->getFloat('score');
    assert(abs($score - 3.14) < 0.001, "Expected score≈3.14, got $score");
    echo "Added FLOAT column 'score' with default=3.14 OK\n";

    // Test 3: Add DOUBLE column with default value
    $c->addColumnDouble('rating', nullable: true, defaultExpr: '9.5');
    $c->flush();
    
    $fetched = $c->fetch('doc1');
    assert(count($fetched) === 1, 'Expected 1 doc');
    $rating = $fetched[0]->getDouble('rating');
    assert(abs($rating - 9.5) < 0.001, "Expected rating≈9.5, got $rating");
    echo "Added DOUBLE column 'rating' with default=9.5 OK\n";

    // Test 4: Verify defaults applied to all existing docs
    $allDocs = $c->fetch('doc1', 'doc2');
    assert(count($allDocs) === 2, 'Expected 2 docs');
    
    foreach ($allDocs as $doc) {
        assert($doc->getInt64('counter') === 100, 'All docs should have counter=100');
        $s = $doc->getFloat('score');
        assert(abs($s - 3.14) < 0.001, 'All docs should have score≈3.14');
        $r = $doc->getDouble('rating');
        assert(abs($r - 9.5) < 0.001, 'All docs should have rating≈9.5');
    }
    echo "All default values applied to existing docs OK\n";

    // Test 5: Verify schema contains new columns
    $schemaStr = $c->schema();
    assert(strpos($schemaStr, "'counter'") !== false, 'Schema should contain counter');
    assert(strpos($schemaStr, "'score'") !== false, 'Schema should contain score');
    assert(strpos($schemaStr, "'rating'") !== false, 'Schema should contain rating');
    echo "New columns appear in schema OK\n";

    // Test 6: Try add column to non-existent collection (should fail)
    $badPath = $path . '_nonexistent';
    try {
        // This will fail because collection doesn't exist
        $badCollection = ZVec::open($badPath);
        // If we get here, the collection was somehow created - shouldn't happen
        echo "FAIL: Should not be able to open non-existent collection\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "Correctly failed to open non-existent collection\n";
    }

    $c->close();
    
    echo "PASS: All addColumn() scenarios work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Added INT64 column 'counter' with default=100 OK
Added FLOAT column 'score' with default=3.14 OK
Added DOUBLE column 'rating' with default=9.5 OK
All default values applied to existing docs OK
New columns appear in schema OK
Correctly failed to open non-existent collection
PASS: All addColumn() scenarios work
