--TEST--
WeightedReRanker: empty weights edge cases (constructor + setter + rerank skip)
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecWeightedReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/reranker_empty_weights_' . uniqid();

try {
    // Test 1: Constructor with empty weights
    try {
        new ZVecWeightedReRanker([]);
        echo "FAIL: Constructor should throw for empty weights\n";
    } catch (ZVecException $e) {
        echo "PASS: Constructor throws for empty weights\n";
    }

    // Test 2: setWeights() with empty values
    $reranker = new ZVecWeightedReRanker(['field1' => 1.0]);
    try {
        $reranker->setWeights([]);
        echo "FAIL: setWeights should throw for empty array\n";
    } catch (ZVecException $e) {
        echo "PASS: setWeights throws for empty array\n";
    }

    // Test 3: setWeights() with valid weights updates the object
    $reranker->setWeights(['field_a' => 0.5, 'field_b' => 0.5]);
    $weights = $reranker->getWeights();
    echo "PASS: setWeights + getWeights count: " . count($weights) . "\n";
    echo "PASS: field_a weight: " . $weights['field_a'] . "\n";

    // Test 4: getTopn / setTopn
    echo "Default topn: " . $reranker->getTopn() . "\n";
    $reranker->setTopn(42);
    echo "After setTopn(42): " . $reranker->getTopn() . "\n";

    // Test 5: getMetricType / setMetricType
    echo "Default metric: " . $reranker->getMetricType() . "\n";
    $reranker->setMetricType(ZVecSchema::METRIC_L2);
    echo "After setMetricType(L2): " . $reranker->getMetricType() . "\n";

    // Test 6: Reranker skips fields not in weights
    $schema = new ZVecSchema('test_skip');
    $schema->addVectorFp32('v', 4, ZVecSchema::METRIC_IP);
    $collection = ZVec::create($path, $schema);

    $doc = new ZVecDoc('only_doc');
    $doc->setVectorFp32('v', [0.5, 0.5, 0.5, 0.5]);
    $collection->insert($doc);
    $collection->optimize();

    $results = $collection->query('v', [1.0, 0.0, 0.0, 0.0], topk: 1);

    // Reranker with weight=0 should produce no weighted contribution
    $zeroWeight = new ZVecWeightedReRanker(
        weights: ['v' => 0.0],
        topn: 1,
        metricType: ZVecSchema::METRIC_IP
    );
    $rerankedZero = $zeroWeight->rerank(['v' => $results]);
    echo "Zero weight reranked count: " . count($rerankedZero) . "\n";
    if (count($rerankedZero) > 0) {
        echo "Zero weight combined score: " . $rerankedZero[0]->getCombinedScore() . "\n";
    }

    // Reranker with negative weight — verify it doesn't crash
    $negativeWeight = new ZVecWeightedReRanker(
        weights: ['v' => -1.0],
        topn: 1,
        metricType: ZVecSchema::METRIC_IP
    );
    $rerankedNeg = $negativeWeight->rerank(['v' => $results]);
    echo "Negative weight reranked count: " . count($rerankedNeg) . "\n";

    $collection->close();
    echo "All empty weights edge case tests passed\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
PASS: Constructor throws for empty weights
PASS: setWeights throws for empty array
PASS: setWeights + getWeights count: 2
PASS: field_a weight: 0.5
Default topn: 10
After setTopn(42): 42
Default metric: 2
After setMetricType(L2): 1
Zero weight reranked count: 0
Negative weight reranked count: 1
All empty weights edge case tests passed
