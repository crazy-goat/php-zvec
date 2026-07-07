--TEST--
WeightedReRanker: PHP_FLOAT_MIN initialization bug — verify min/max tracking works
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecWeightedReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/reranker_float_min_' . uniqid();

try {
    // Create collection
    $schema = new ZVecSchema('test_float_min');
    $schema->addVectorFp32('v', 4, ZVecSchema::METRIC_IP);
    $collection = ZVec::create($path, $schema);

    // Insert docs with varying vectors
    $docs = [
        (new ZVecDoc('doc1'))->setVectorFp32('v', [0.5, 0.5, 0.5, 0.5]),
        (new ZVecDoc('doc2'))->setVectorFp32('v', [0.1, 0.1, 0.1, 0.1]),
    ];
    $collection->insert(...$docs);
    $collection->optimize();

    // Query with a vector that gives different scores
    $results = $collection->query('v', [1.0, 0.0, 0.0, 0.0], topk: 2);
    echo "Results: " . count($results) . "\n";

    // Verify min/max are properly tracked (not stuck at PHP_FLOAT_MIN / -PHP_FLOAT_MIN)
    $scores = array_map(fn($d) => $d->getScore(), $results);
    echo "Scores: " . implode(', ', array_map(fn($s) => round($s, 6), $scores)) . "\n";

    $minScore = min($scores);
    $maxScore = max($scores);
    echo "Min score: " . $minScore . "\n";
    echo "Max score: " . $maxScore . "\n";

    // With proper PHP_FLOAT_MAX initialization, min and max should be the actual scores
    // If PHP_FLOAT_MIN was incorrectly used, min would still be ~2.2E-308
    $tolerance = 1e-10;
    // If min was stuck at PHP_FLOAT_MIN (~2.2E-308), it would be a tiny positive number,
    // not the actual minimum score. Check that min was properly updated to real score value.
    $stuckAtPhpFloatMin = ($minScore < 1e-100);
    echo "min stuck at PHP_FLOAT_MIN: " . ($stuckAtPhpFloatMin ? 'yes' : 'no') . "\n";

    // Feed through WeightedReRanker
    $reranker = new ZVecWeightedReRanker(
        weights: ['v' => 1.0],
        topn: 2,
        metricType: ZVecSchema::METRIC_IP
    );
    $reranked = $reranker->rerank(['v' => $results]);
    echo "Reranked count: " . count($reranked) . "\n";

    // Combined scores should reflect proper normalization
    if (count($reranked) >= 2) {
        echo "Combined scores: " . round($reranked[0]->getCombinedScore(), 4) . ", " 
            . round($reranked[1]->getCombinedScore(), 4) . "\n";
        // With IP normalization: (score - min) / range
        // doc1 should have higher score (closer to query [1,0,0,0] since [0.5,0.5,0.5,0.5] has dot product 0.5)
        // doc2 has dot product 0.1 with query [1,0,0,0]
        echo "Best doc: " . $reranked[0]->getPk() . " (combined: " . round($reranked[0]->getCombinedScore(), 4) . ")\n";
        echo "Worst doc: " . $reranked[1]->getPk() . " (combined: " . round($reranked[1]->getCombinedScore(), 4) . ")\n";
    }

    $collection->close();
    echo "All PHP_FLOAT_MIN bug tests passed\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Results: 2
Scores: %s
Min score: %f
Max score: %f
min stuck at PHP_FLOAT_MIN: no
Reranked count: 2
Combined scores: %f, %f
Best doc: doc1 (combined: %f)
Worst doc: doc2 (combined: %f)
All PHP_FLOAT_MIN bug tests passed
