--TEST--
query() returns ZVecDoc[], queryWithReranker() returns ZVecRerankedDoc[]
--SKIPIF--
<?php
if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available');
require_once __DIR__ . '/../src/ZVec.php';
if (!method_exists('ZVec', 'queryWithReranker')) die('skip queryWithReranker() not available in native extension');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecRrfReRanker.php';
require_once __DIR__ . '/../src/ZVecWeightedReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_return_types_' . uniqid();
try {
    $schema = new ZVecSchema('test_return_types');
    $schema->addString('title', withInvertIndex: false);
    $schema->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_L2);

    $collection = ZVec::create($path, $schema);
    $collection->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_L2, m: 16, efConstruction: 40);

    $docs = [
        ['id' => 'doc1', 'title' => 'PHP', 'embedding' => [1.0, 0.0, 0.0, 0.0]],
        ['id' => 'doc2', 'title' => 'Python', 'embedding' => [0.9, 0.1, 0.0, 0.0]],
        ['id' => 'doc3', 'title' => 'JavaScript', 'embedding' => [0.8, 0.2, 0.0, 0.0]],
    ];
    foreach ($docs as $data) {
        $doc = new ZVecDoc($data['id']);
        $doc->setString('title', $data['title']);
        $doc->setVectorFp32('embedding', $data['embedding']);
        $collection->insert($doc);
    }
    $collection->optimize();

    // Test 1: query() without reranker returns ZVecDoc[]
    echo "Test 1: query() returns ZVecDoc[]\n";
    $results = $collection->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 3);
    assert(is_array($results), "query() must return array");
    assert(count($results) === 3, "Expected 3 results");
    assert($results[0] instanceof ZVecDoc, "query() without reranker must return ZVecDoc[]");
    echo "  PASS: " . count($results) . " ZVecDoc results\n";

    // Test 2: queryWithReranker() returns ZVecRerankedDoc[]
    echo "\nTest 2: queryWithReranker() returns ZVecRerankedDoc[]\n";
    $rrfReranker = new ZVecRrfReRanker(topn: 3, rankConstant: 60);
    $results = $collection->queryWithReranker(
        fieldName: 'embedding',
        queryVector: [1.0, 0.0, 0.0, 0.0],
        topk: 3,
        reranker: $rrfReranker
    );
    assert(is_array($results), "queryWithReranker() must return array");
    assert(count($results) === 3, "Expected 3 results");
    assert($results[0] instanceof ZVecRerankedDoc, "queryWithReranker() must return ZVecRerankedDoc[]");
    assert($results[0]->getPk() === 'doc1', "First result should be doc1");
    assert($results[0]->getCombinedScore() > 0, "combinedScore must be positive");
    echo "  PASS: " . count($results) . " ZVecRerankedDoc results\n";

    // Test 3: queryWithReranker() with ZVecVectorQuery
    echo "\nTest 3: queryWithReranker() with ZVecVectorQuery\n";
    $vq = new ZVecVectorQuery(fieldName: 'embedding', vector: [1.0, 0.0, 0.0, 0.0]);
    $results = $collection->queryWithReranker(
        fieldName: $vq,
        topk: 3,
        reranker: $rrfReranker
    );
    assert($results[0] instanceof ZVecRerankedDoc, "queryWithReranker(VectorQuery) must return ZVecRerankedDoc[]");
    echo "  PASS: ZVecRerankedDoc with VectorQuery\n";

    // Test 4: queryWithReranker() with WeightedReRanker
    echo "\nTest 4: queryWithReranker() with WeightedReRanker\n";
    $weightedReranker = new ZVecWeightedReRanker(
        topn: 3,
        metricType: ZVecSchema::METRIC_L2,
        weights: ['embedding' => 1.0]
    );
    $results = $collection->queryWithReranker(
        fieldName: 'embedding',
        queryVector: [1.0, 0.0, 0.0, 0.0],
        topk: 3,
        reranker: $weightedReranker
    );
    assert($results[0] instanceof ZVecRerankedDoc, "queryWithReranker(Weighted) must return ZVecRerankedDoc[]");
    echo "  PASS: ZVecRerankedDoc with WeightedReRanker\n";

    // Test 5: deprecated query($reranker=...) issues E_USER_DEPRECATED
    echo "\nTest 5: query(\$reranker) issues E_USER_DEPRECATED\n";
    $deprecatedTriggered = false;
    set_error_handler(function ($errno, $errstr) use (&$deprecatedTriggered) {
        if ($errno === E_USER_DEPRECATED) {
            $deprecatedTriggered = true;
            assert(str_contains($errstr, 'deprecated'), "Error message should mention deprecated");
            assert(str_contains($errstr, 'queryWithReranker'), "Error message should suggest queryWithReranker()");
            return true;
        }
        return false;
    });
    $results = $collection->query(
        'embedding',
        [1.0, 0.0, 0.0, 0.0],
        topk: 3,
        reranker: $rrfReranker
    );
    restore_error_handler();
    assert($deprecatedTriggered, "query(\$reranker) must trigger E_USER_DEPRECATED");
    assert($results[0] instanceof ZVecRerankedDoc, "Deprecated path should still return ZVecRerankedDoc[] for BC");
    echo "  PASS: E_USER_DEPRECATED triggered\n";

    // Test 6: query() without reranker does NOT trigger deprecation
    echo "\nTest 6: query() without reranker does NOT trigger deprecation\n";
    $deprecatedTriggered = false;
    set_error_handler(function ($errno) use (&$deprecatedTriggered) {
        if ($errno === E_USER_DEPRECATED) {
            $deprecatedTriggered = true;
            return true;
        }
        return false;
    });
    $results = $collection->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 3);
    restore_error_handler();
    assert(!$deprecatedTriggered, "query() without reranker should NOT trigger deprecation");
    assert($results[0] instanceof ZVecDoc, "query() without reranker must return ZVecDoc[]");
    echo "  PASS: No deprecation without reranker\n";

    $collection->close();
    echo "\nAll return type tests passed!\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Test 1: query() returns ZVecDoc[]
  PASS: 3 ZVecDoc results

Test 2: queryWithReranker() returns ZVecRerankedDoc[]
  PASS: 3 ZVecRerankedDoc results

Test 3: queryWithReranker() with ZVecVectorQuery
  PASS: ZVecRerankedDoc with VectorQuery

Test 4: queryWithReranker() with WeightedReRanker
  PASS: ZVecRerankedDoc with WeightedReRanker

Test 5: query($reranker) issues E_USER_DEPRECATED
  PASS: E_USER_DEPRECATED triggered

Test 6: query() without reranker does NOT trigger deprecation
  PASS: No deprecation without reranker

All return type tests passed!
