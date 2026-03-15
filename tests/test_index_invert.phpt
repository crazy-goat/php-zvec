--TEST--
Inverted index: createInvertIndex, dropIndex, filter queries
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/index_invert_' . uniqid();

try {
    // Create schema without invert index initially
    $schema = new ZVecSchema('invert_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)  // No invert index initially
        ->addInt64('category', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert test docs
    $categories = [1, 2, 1, 3, 2];
    for ($i = 1; $i <= 5; $i++) {
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)
            ->setInt64('category', $categories[$i - 1])
            ->setVectorFp32('v', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i]);
        $c->insert($doc);
    }
    $c->optimize();

    // Test 1: Query without invert index (brute force scan)
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 5,
        filter: "category = 1"
    );
    assert(count($results) == 2, "Expected 2 docs with category=1, got " . count($results));
    echo "Query without invert index works (brute force filter)\n";

    // Test 2: Create invert index on category field
    $c->createInvertIndex('category', enableRange: true, enableWildcard: false);
    $c->flush();
    
    echo "Created invert index on 'category' field\n";

    // Test 3: Query with invert index (should be faster, same results)
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 5,
        filter: "category = 1"
    );
    assert(count($results) == 2, "Expected 2 docs with category=1 after index");
    
    // Verify we got the right docs (doc1 and doc3)
    $ids = array_map(fn($r) => $r->getPk(), $results);
    sort($ids);
    assert($ids === ['doc1', 'doc3'], "Expected doc1 and doc3, got " . implode(', ', $ids));
    echo "Query with invert index returns correct results\n";

    // Test 4: Range queries with invert index
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 5,
        filter: "category >= 2"
    );
    assert(count($results) === 3, "Expected 3 docs with category>=2");
    echo "Range filter works with invert index\n";

    // Test 5: Create invert index on id field
    $c->createInvertIndex('id', enableRange: true, enableWildcard: false);
    $c->flush();
    
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 3,
        filter: "id <= 3"
    );
    assert(count($results) === 3, "Expected 3 docs with id<=3");
    echo "Created invert index on 'id' field, range query works\n";

    // Test 6: Drop index on category
    $c->dropIndex('category');
    $c->flush();
    
    // Query should still work (fallback to brute force)
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 5,
        filter: "category = 1"
    );
    assert(count($results) == 2, "Query still works after dropping index");
    echo "Dropped invert index on 'category', query still works\n";

    // Test 7: Recreate index with different settings
    $c->createInvertIndex('category', enableRange: false, enableWildcard: false);
    $c->flush();
    
    // Equality query should still work
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 5,
        filter: "category = 2"
    );
    assert(count($results) == 2, "Expected 2 docs with category=2 after recreate");
    echo "Recreated invert index with range disabled, equality queries work\n";

    // Test 8: Try create invert index on non-existent field (should fail)
    try {
        $c->createInvertIndex('nonexistent', enableRange: true);
        echo "FAIL: Should not be able to create index on non-existent field\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "Correctly rejected creating index on non-existent field\n";
    }

    // Test 9: Try drop index on non-indexed field (should fail or succeed silently)
    try {
        $c->dropIndex('nonexistent');
        echo "Note: Dropping non-existent index succeeded\n";
    } catch (ZVecException $e) {
        echo "Correctly rejected dropping non-existent index\n";
    }

    $c->close();
    
    echo "PASS: All invert index operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Query without invert index works (brute force filter)
Created invert index on 'category' field
Query with invert index returns correct results
Range filter works with invert index
Created invert index on 'id' field, range query works
Dropped invert index on 'category', query still works
Recreated invert index with range disabled, equality queries work
Correctly rejected creating index on non-existent field
Correctly rejected dropping non-existent index
PASS: All invert index operations work
