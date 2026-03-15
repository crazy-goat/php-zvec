--TEST--
Query with reranker parameter: RRF and Weighted reranker integration
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
require_once __DIR__ . '/../php/ZVecRrfReRanker.php';
require_once __DIR__ . '/../php/ZVecWeightedReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_reranker_' . uniqid();
try {
    // Create schema with vector field
    $schema = new ZVecSchema('test_query_reranker');
    $schema->addString('title', withInvertIndex: false);
    $schema->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_L2);

    // Create and populate collection
    $collection = ZVec::create($path, $schema);
    $collection->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_L2, m: 16, efConstruction: 40);

    // Insert test documents
    $docs = [
        ['id' => 'doc1', 'title' => 'PHP Programming', 'embedding' => [1.0, 0.0, 0.0, 0.0]],
        ['id' => 'doc2', 'title' => 'Python Programming', 'embedding' => [0.9, 0.1, 0.0, 0.0]],
        ['id' => 'doc3', 'title' => 'JavaScript Basics', 'embedding' => [0.8, 0.2, 0.0, 0.0]],
        ['id' => 'doc4', 'title' => 'Web Development', 'embedding' => [0.7, 0.3, 0.0, 0.0]],
        ['id' => 'doc5', 'title' => 'Database Design', 'embedding' => [0.6, 0.4, 0.0, 0.0]],
        ['id' => 'doc6', 'title' => 'System Architecture', 'embedding' => [0.5, 0.5, 0.0, 0.0]],
        ['id' => 'doc7', 'title' => 'Cloud Computing', 'embedding' => [0.4, 0.6, 0.0, 0.0]],
        ['id' => 'doc8', 'title' => 'Machine Learning', 'embedding' => [0.3, 0.7, 0.0, 0.0]],
        ['id' => 'doc9', 'title' => 'Data Science', 'embedding' => [0.2, 0.8, 0.0, 0.0]],
        ['id' => 'doc10', 'title' => 'AI Research', 'embedding' => [0.1, 0.9, 0.0, 0.0]],
    ];

    foreach ($docs as $data) {
        $doc = new ZVecDoc($data['id']);  // Pass PK to constructor
        $doc->setString('title', $data['title']);
        $doc->setVectorFp32('embedding', $data['embedding']);
        $collection->insert($doc);
    }
    $collection->optimize();

    // Test 1: Query without reranker (baseline)
    echo "Test 1: Query without reranker\n";
    $results = $collection->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 5);
    assert(count($results) === 5, "Expected 5 results without reranker");
    assert($results[0] instanceof ZVecDoc, "Results should be ZVecDoc objects");
    assert($results[0]->getPk() === 'doc1', "First result should be doc1");
    echo "  - Got " . count($results) . " ZVecDoc results\n";

    // Test 2: Query with RRF reranker (two-stage retrieval)
    echo "\nTest 2: Query with RRF reranker (two-stage retrieval)\n";
    $rrfReranker = new ZVecRrfReRanker(topn: 3, rankConstant: 60);
    $results = $collection->query(
        'embedding',
        [1.0, 0.0, 0.0, 0.0],
        topk: 3,
        reranker: $rrfReranker
    );
    assert(count($results) === 3, "Expected 3 results after reranking");
    assert($results[0] instanceof ZVecRerankedDoc, "Results should be ZVecRerankedDoc objects");
    assert($results[0]->getPk() === 'doc1', "First reranked result should be doc1");
    assert($results[0]->combinedScore > 0, "Combined score should be positive");
    echo "  - Got " . count($results) . " ZVecRerankedDoc results\n";
    echo "  - First result: " . $results[0]->getPk() . " (score: " . round($results[0]->combinedScore, 4) . ")\n";

    // Test 3: Query with Weighted reranker
    echo "\nTest 3: Query with Weighted reranker\n";
    $weightedReranker = new ZVecWeightedReRanker(
        topn: 3,
        metricType: ZVecSchema::METRIC_L2,
        weights: ['embedding' => 1.0]
    );
    $results = $collection->query(
        'embedding',
        [1.0, 0.0, 0.0, 0.0],
        topk: 3,
        reranker: $weightedReranker
    );
    assert(count($results) === 3, "Expected 3 results after weighted reranking");
    assert($results[0] instanceof ZVecRerankedDoc, "Results should be ZVecRerankedDoc objects");
    assert($results[0]->getPk() === 'doc1', "First weighted result should be doc1");
    echo "  - Got " . count($results) . " ZVecRerankedDoc results\n";

    // Test 4: Query with ZVecVectorQuery and reranker
    echo "\nTest 4: Query with ZVecVectorQuery and reranker\n";
    $vq = new ZVecVectorQuery(
        fieldName: 'embedding',
        vector: [0.0, 1.0, 0.0, 0.0]
    );
    $results = $collection->query($vq, topk: 3, reranker: $rrfReranker);
    assert(count($results) === 3, "Expected 3 results with VectorQuery");
    assert($results[0] instanceof ZVecRerankedDoc, "Results should be ZVecRerankedDoc");
    // With [0,1,0,0] query, doc10 (AI Research with [0.1, 0.9...]) should be closest
    echo "  - Got " . count($results) . " results with VectorQuery\n";
    echo "  - First result: " . $results[0]->getPk() . "\n";

    $collection->close();

    echo "\nAll reranker parameter tests passed!\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Test 1: Query without reranker
  - Got 5 ZVecDoc results

Test 2: Query with RRF reranker (two-stage retrieval)
  - Got 3 ZVecRerankedDoc results
  - First result: doc1 (score: 0.0164)

Test 3: Query with Weighted reranker
  - Got 3 ZVecRerankedDoc results

Test 4: Query with ZVecVectorQuery and reranker
  - Got 3 results with VectorQuery
  - First result: doc10

All reranker parameter tests passed!
