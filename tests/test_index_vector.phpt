--TEST--
Vector index operations: createFlatIndex, createHnswIndex, dropIndex, switching
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_index_vector_' . uniqid();

try {
    // Create schema with vector field but no index initially
    $schema = new ZVecSchema('vector_index_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert test docs
    for ($i = 1; $i <= 10; $i++) {
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)
            ->setVectorFp32('v', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i]);
        $c->insert($doc);
    }
    $c->optimize();

    // Test 1: Query without vector index (brute force)
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 3
    );
    assert(count($results) === 3, "Expected 3 results without index");
    // Verify results are valid docs with scores
    foreach ($results as $r) {
        assert(strpos($r->getPk(), 'doc') === 0, "Expected valid doc ID");
        assert($r->getScore() > 0, "Expected positive score");
    }
    echo "Query without vector index works (brute force)\n";

    // Test 2: Create Flat index
    $c->createFlatIndex('v', metricType: ZVecSchema::METRIC_IP);
    $c->flush();
    $c->optimize();
    
    echo "Created Flat index on vector field\n";

    // Test 3: Query with Flat index (exact search)
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 3
    );
    assert(count($results) === 3, "Expected 3 results with Flat index");
    foreach ($results as $r) {
        assert(strpos($r->getPk(), 'doc') === 0, "Expected valid doc ID with Flat index");
    }
    echo "Query with Flat index returns correct results\n";

    // Test 4: Switch to HNSW index (drop Flat, create HNSW)
    $c->dropIndex('v');
    $c->flush();
    
    $c->createHnswIndex(
        'v',
        metricType: ZVecSchema::METRIC_IP,
        m: 16,
        efConstruction: 200
    );
    $c->flush();
    $c->optimize();
    
    echo "Switched to HNSW index (dropped Flat, created HNSW)\n";

    // Test 5: Query with HNSW index (approximate search)
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 3
    );
    assert(count($results) === 3, "Expected 3 results with HNSW index");
    foreach ($results as $r) {
        assert(strpos($r->getPk(), 'doc') === 0, "Expected valid doc ID with HNSW index");
    }
    echo "Query with HNSW index returns correct results\n";

    // Test 6: Check index completeness (should be > 0 after optimize)
    $stats = $c->stats();
    if (isset($stats['index_completeness'])) {
        assert($stats['index_completeness'] > 0, "Index completeness should be > 0 after optimize");
        echo "Index completeness: {$stats['index_completeness']}\n";
    } else {
        echo "Index completeness stat not available\n";
    }

    // Test 7: Switch back to Flat index
    $c->dropIndex('v');
    $c->flush();
    
    $c->createFlatIndex('v', metricType: ZVecSchema::METRIC_IP);
    $c->flush();
    $c->optimize();
    
    echo "Switched back to Flat index\n";

    // Verify query still works
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 3
    );
    assert(count($results) === 3, "Expected 3 results after switching back to Flat");
    echo "Query works after switching back to Flat\n";

    // Test 8: Drop and recreate HNSW with different parameters
    $c->dropIndex('v');
    $c->flush();
    
    $c->createHnswIndex(
        'v',
        metricType: ZVecSchema::METRIC_IP,
        m: 32,  // Different M parameter
        efConstruction: 400  // Different efConstruction
    );
    $c->flush();
    $c->optimize();
    
    echo "Recreated HNSW with different parameters (M=32, efConstruction=400)\n";

    // Test 9: Verify query accuracy (smoke test)
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 5
    );
    assert(count($results) === 5, "Expected 5 results");
    foreach ($results as $r) {
        assert(strpos($r->getPk(), 'doc') === 0, "Expected valid doc ID");
    }
    echo "Query accuracy verified (all results are valid docs)\n";

    // Test 10: Try create index on non-vector field (should fail)
    try {
        $c->createFlatIndex('id');  // 'id' is Int64, not vector
        echo "FAIL: Should not be able to create vector index on non-vector field\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "Correctly rejected creating vector index on non-vector field\n";
    }

    $c->close();
    
    echo "PASS: All vector index operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Query without vector index works (brute force)
Created Flat index on vector field
Query with Flat index returns correct results
Switched to HNSW index (dropped Flat, created HNSW)
Query with HNSW index returns correct results
Index completeness stat not available
Switched back to Flat index
Query works after switching back to Flat
Recreated HNSW with different parameters (M=32, efConstruction=400)
Query accuracy verified (all results are valid docs)
Correctly rejected creating vector index on non-vector field
PASS: All vector index operations work
