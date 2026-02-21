--TEST--
Query operations: filtered vector search and filter-only queries
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_filtered_' . uniqid();

try {
    $schema = new ZVecSchema('filtered_query_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('category', nullable: true, withInvertIndex: true)
        ->addString('status', nullable: true, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Insert documents with different categories and vectors
    $docs = [
        ['doc1', 1, [1.0, 0.0, 0.0, 0.0], 'tech', 'active', 90.0],
        ['doc2', 2, [0.9, 0.1, 0.0, 0.0], 'tech', 'active', 85.0],
        ['doc3', 3, [0.8, 0.2, 0.0, 0.0], 'tech', 'inactive', 80.0],
        ['doc4', 4, [0.5, 0.5, 0.0, 0.0], 'finance', 'active', 75.0],
        ['doc5', 5, [0.4, 0.6, 0.0, 0.0], 'finance', 'active', 70.0],
        ['doc6', 6, [0.3, 0.7, 0.0, 0.0], 'finance', 'inactive', 65.0],
    ];

    foreach ($docs as $d) {
        $doc = new ZVecDoc($d[0]);
        $doc->setInt64('id', $d[1])
            ->setVectorFp32('embedding', $d[2])
            ->setString('category', $d[3])
            ->setString('status', $d[4])
            ->setFloat('score', $d[5]);
        $c->insert($doc);
    }
    echo "Inserted 6 documents\n";

    $c->optimize();
    echo "Optimized\n";

    // Test 1: Vector search with simple filter
    // Query for tech category only - should return tech docs ordered by similarity
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 10, filter: "category = 'tech'");
    assert(count($results) === 3, 'Should return 3 tech documents');
    
    // Verify all results are tech category
    foreach ($results as $doc) {
        assert($doc->getString('category') === 'tech', 'All results should be tech category');
    }
    
    // Verify ordering (doc1 should be first as it's most similar)
    assert($results[0]->getPk() === 'doc1', 'doc1 should be first (exact match)');
    assert($results[1]->getPk() === 'doc2', 'doc2 should be second');
    echo "Vector search with category filter OK\n";

    // Test 2: Vector search with compound filter (AND)
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 10, filter: "category = 'tech' AND status = 'active'");
    assert(count($results) === 2, 'Should return 2 active tech documents');
    foreach ($results as $doc) {
        assert($doc->getString('category') === 'tech', 'Category should be tech');
        assert($doc->getString('status') === 'active', 'Status should be active');
    }
    echo "Vector search with compound AND filter OK\n";

    // Test 3: Vector search with OR condition
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 10, filter: "category = 'tech' OR score > 80");
    // Should return doc1, doc2, doc3 (tech) and doc4 (score=75 - no, that's not >80)
    // Actually: doc1 (90), doc2 (85), doc3 (80) - all tech, none from finance have score >80
    assert(count($results) >= 3, 'Should return at least 3 documents');
    echo "Vector search with OR filter OK\n";

    // Test 4: Filter with numeric comparisons
    $results = $c->query('embedding', [0.5, 0.5, 0.0, 0.0], topk: 10, filter: "score >= 70");
    assert(count($results) === 5, 'Should return 5 docs with score >= 70');
    foreach ($results as $doc) {
        assert($doc->getFloat('score') >= 70, 'All scores should be >= 70');
    }
    echo "Vector search with numeric filter OK\n";

    // Test 5: Filter only (no vector) using queryByFilter
    $results = $c->queryByFilter("category = 'finance'", topk: 10);
    assert(count($results) === 3, 'Should return 3 finance documents');
    foreach ($results as $doc) {
        assert($doc->getString('category') === 'finance', 'All should be finance');
    }
    echo "Filter only (queryByFilter) OK\n";

    // Test 6: Filter only with complex condition
    $results = $c->queryByFilter("category = 'tech' AND status = 'inactive'", topk: 10);
    assert(count($results) === 1, 'Should return 1 inactive tech doc');
    assert($results[0]->getPk() === 'doc3', 'Should be doc3');
    echo "Filter only with complex condition OK\n";

    // Test 7: Filter only with IN operator
    $results = $c->queryByFilter("id IN (1, 3, 5)", topk: 10);
    assert(count($results) === 3, 'Should return 3 documents');
    $ids = array_map(fn($d) => $d->getInt64('id'), $results);
    sort($ids);
    assert($ids === [1, 3, 5], 'Should be docs with id 1, 3, 5');
    echo "Filter only with IN operator OK\n";

    // Test 8: Filter matching 0 results
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 10, filter: "category = 'nonexistent'");
    assert(count($results) === 0, 'Should return empty for non-matching filter');
    echo "Filter matching 0 results OK\n";

    $results = $c->queryByFilter("category = 'nonexistent'", topk: 10);
    assert(count($results) === 0, 'queryByFilter should return empty for non-matching filter');
    echo "queryByFilter with 0 matches OK\n";

    // Test 9: Complex filter with multiple conditions
    $results = $c->queryByFilter("(category = 'tech' OR category = 'finance') AND status = 'active'", topk: 10);
    assert(count($results) === 4, 'Should return 4 active docs from both categories');
    foreach ($results as $doc) {
        assert($doc->getString('status') === 'active', 'All should be active');
    }
    echo "Complex multi-condition filter OK\n";

    // Test 10: Filter with topk limiting results
    $results = $c->queryByFilter("status = 'active'", topk: 2);
    assert(count($results) === 2, 'Should return only 2 results due to topk limit');
    echo "Filter with topk limit OK\n";

    $c->close();
    echo "PASS: Filtered query operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 6 documents
Optimized
Vector search with category filter OK
Vector search with compound AND filter OK
Vector search with OR filter OK
Vector search with numeric filter OK
Filter only (queryByFilter) OK
Filter only with complex condition OK
Filter only with IN operator OK
Filter matching 0 results OK
queryByFilter with 0 matches OK
Complex multi-condition filter OK
Filter with topk limit OK
PASS: Filtered query operations work
