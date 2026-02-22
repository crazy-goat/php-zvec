--TEST--
Query operations: query by document ID
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_by_id_' . uniqid();

try {
    $schema = new ZVecSchema('query_by_id_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('category', nullable: true, withInvertIndex: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Insert documents with different vectors
    $docs = [
        ['doc1', 1, [1.0, 0.0, 0.0, 0.0], 'tech'],
        ['doc2', 2, [0.9, 0.1, 0.0, 0.0], 'tech'],
        ['doc3', 3, [0.8, 0.2, 0.0, 0.0], 'tech'],
        ['doc4', 4, [0.5, 0.5, 0.0, 0.0], 'finance'],
        ['doc5', 5, [0.4, 0.6, 0.0, 0.0], 'finance'],
        ['doc6', 6, [0.3, 0.7, 0.0, 0.0], 'finance'],
    ];

    foreach ($docs as $d) {
        $doc = new ZVecDoc($d[0]);
        $doc->setInt64('id', $d[1])
            ->setVectorFp32('embedding', $d[2])
            ->setString('category', $d[3]);
        $c->insert($doc);
    }
    echo "Inserted 6 documents\n";

    $c->optimize();
    echo "Optimized\n";

    // Test 1: Basic queryById - find similar to doc1
    $results = $c->queryById('embedding', 'doc1', topk: 5);
    assert(count($results) === 5, 'Should return 5 similar documents');
    
    // First result should be doc1 itself (exact match)
    assert($results[0]->getPk() === 'doc1', 'First result should be doc1 (exact match)');
    
    // Remaining results should be ordered by similarity to doc1
    // doc2 (0.9 similarity) should come before doc3 (0.8 similarity)
    assert($results[1]->getPk() === 'doc2', 'Second result should be doc2');
    assert($results[2]->getPk() === 'doc3', 'Third result should be doc3');
    echo "Basic queryById OK\n";

    // Test 2: queryById with filter
    $results = $c->queryById('embedding', 'doc1', topk: 5, filter: "category = 'tech'");
    assert(count($results) === 3, 'Should return 3 tech documents');
    foreach ($results as $doc) {
        assert($doc->getString('category') === 'tech', 'All results should be tech category');
    }
    echo "queryById with filter OK\n";

    // Test 3: queryById with output fields
    $results = $c->queryById('embedding', 'doc4', topk: 3, outputFields: ['id', 'category']);
    assert(count($results) === 3, 'Should return 3 results');
    // Should not include vector since includeVector is false by default
    echo "queryById with output fields OK\n";

    // Test 4: queryById with HNSW query params
    $results = $c->queryById('embedding', 'doc2', topk: 5, 
        queryParamType: ZVec::QUERY_PARAM_HNSW, 
        hnswEf: 50
    );
    assert(count($results) === 5, 'Should return 5 results with HNSW params');
    echo "queryById with HNSW params OK\n";

    // Test 5: queryById with non-existent document
    try {
        $c->queryById('embedding', 'nonexistent_doc', topk: 5);
        assert(false, 'Should throw exception for non-existent doc');
    } catch (ZVecException $e) {
        assert(strpos($e->getMessage(), 'Document not found') !== false, 'Should report document not found');
        echo "queryById with non-existent doc throws exception OK\n";
    }

    // Test 6: queryById excludes the source document when looking for similar
    // Query using doc4 and ensure we get meaningful results
    $results = $c->queryById('embedding', 'doc4', topk: 5);
    assert(count($results) >= 1, 'Should return at least 1 result');
    // doc4 itself should be included (exact match)
    $foundDoc4 = false;
    foreach ($results as $doc) {
        if ($doc->getPk() === 'doc4') {
            $foundDoc4 = true;
            break;
        }
    }
    assert($foundDoc4, 'doc4 should be in results (exact match)');
    echo "queryById returns source doc and similar docs OK\n";

    $c->close();
    echo "PASS: queryById operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 6 documents
Optimized
Basic queryById OK
queryById with filter OK
queryById with output fields OK
queryById with HNSW params OK
queryById with non-existent doc throws exception OK
queryById returns source doc and similar docs OK
PASS: queryById operations work
