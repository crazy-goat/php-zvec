--TEST--
WeightedReRanker: L2 metric normalization — lower distance = higher combined score
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecWeightedReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/reranker_l2_' . uniqid();

try {
    // Create collection with METRIC_L2
    $schema = new ZVecSchema('test_l2_rerank');
    $schema->addVectorFp32('v', 4, ZVecSchema::METRIC_L2);
    $collection = ZVec::create($path, $schema);

    // Insert docs with different distances from the query
    // Query vector: [0, 0, 0, 0]
    // doc1: [1,0,0,0] => L2 distance = 1.0
    // doc2: [2,0,0,0] => L2 distance = 2.0
    // doc3: [3,0,0,0] => L2 distance = 3.0
    $docs = [
        (new ZVecDoc('near'))->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0]),
        (new ZVecDoc('mid'))->setVectorFp32('v', [2.0, 0.0, 0.0, 0.0]),
        (new ZVecDoc('far'))->setVectorFp32('v', [3.0, 0.0, 0.0, 0.0]),
    ];
    $collection->insert(...$docs);
    $collection->optimize();

    // Query with [0,0,0,0]
    $results = $collection->query('v', [0.0, 0.0, 0.0, 0.0], topk: 3);
    echo "Query returned " . count($results) . " results\n";

    // L2 score = distance from query, so smaller = better
    $scores = array_map(fn($d) => $d->getScore(), $results);
    echo "L2 scores (distance): " . implode(', ', array_map(fn($s) => round($s, 4), $scores)) . "\n";
    echo "Order (should be near < mid < far): " . implode(', ', array_map(fn($d) => $d->getPk(), $results)) . "\n";

    // WeightedReRanker with L2 metric
    $reranker = new ZVecWeightedReRanker(
        weights: ['v' => 1.0],
        topn: 3,
        metricType: ZVecSchema::METRIC_L2
    );
    $reranked = $reranker->rerank(['v' => $results]);
    echo "Reranked count: " . count($reranked) . "\n";

    // With L2 normalization: (max - score) / range
    // 'near' (dist=1.0) should have highest combined score
    // 'far' (dist=3.0) should have lowest combined score
    echo "Reranked order: " . implode(', ', array_map(fn($r) => $r->getPk(), $reranked)) . "\n";
    echo "Combined scores: " . implode(', ', array_map(fn($r) => round($r->getCombinedScore(), 4), $reranked)) . "\n";

    if (count($reranked) >= 3) {
        $first = $reranked[0];
        $last = $reranked[2];
        echo "Highest combined score: " . $first->getPk() . " (" . round($first->getCombinedScore(), 4) . ")\n";
        echo "Lowest combined score: " . $last->getPk() . " (" . round($last->getCombinedScore(), 4) . ")\n";

        // Verify the order: near (smallest L2) should be first
        $isCorrectOrder = $reranked[0]->getPk() === 'near' 
            && $reranked[1]->getPk() === 'mid' 
            && $reranked[2]->getPk() === 'far';
        echo "L2 normalization order correct: " . ($isCorrectOrder ? 'yes' : 'no') . "\n";

        // Verify combined scores are decreasing
        $cs0 = $reranked[0]->getCombinedScore();
        $cs1 = $reranked[1]->getCombinedScore();
        $cs2 = $reranked[2]->getCombinedScore();
        echo "Score order check (should be decreasing): $cs0 >= $cs1 >= $cs2 — " 
            . ($cs0 >= $cs1 && $cs1 >= $cs2 ? 'PASS' : 'FAIL') . "\n";
    }

    $collection->close();
    echo "All L2 metric tests passed\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Query returned 3 results
L2 scores (distance): %f, %f, %f
Order (should be near < mid < far): near, mid, far
Reranked count: 3
Reranked order: near, mid, far
Combined scores: %s
Highest combined score: near (%f)
Lowest combined score: far (%f)
L2 normalization order correct: yes
Score order check (should be decreasing): %f >= %f >= %f — PASS
All L2 metric tests passed
