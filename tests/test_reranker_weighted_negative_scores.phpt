--TEST--
WeightedReRanker: negative IP scores — normalization handles negative values correctly
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecWeightedReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/reranker_neg_' . uniqid();

try {
    // Create collection with METRIC_IP
    $schema = new ZVecSchema('test_neg_scores');
    $schema->addVectorFp32('v', 4, ZVecSchema::METRIC_IP);
    $collection = ZVec::create($path, $schema);

    // Query vector: [1, 0, 0, 0]
    // doc_pos: [1, 0, 0, 0] => IP = 1.0 (positive, most similar)
    // doc_neg: [-1, 0, 0, 0] => IP = -1.0 (negative, opposite direction)
    // doc_zero: [0, 1, 0, 0] => IP = 0.0 (orthogonal)
    $docs = [
        (new ZVecDoc('positive'))->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0]),
        (new ZVecDoc('negative'))->setVectorFp32('v', [-0.5, 0.0, 0.0, 0.0]),
        (new ZVecDoc('near_zero'))->setVectorFp32('v', [0.0, 1.0, 0.0, 0.0]),
    ];
    $collection->insert(...$docs);
    $collection->optimize();

    // Query with [1, 0, 0, 0]
    $results = $collection->query('v', [1.0, 0.0, 0.0, 0.0], topk: 3);
    echo "Query returned " . count($results) . " results\n";

    // Verify we have a mix of positive, zero, and negative scores
    $scores = array_map(fn($d) => $d->getScore(), $results);
    echo "Raw IP scores: " . implode(', ', array_map(fn($s) => round($s, 4), $scores)) . "\n";

    $hasNegative = count(array_filter($scores, fn($s) => $s < 0)) > 0;
    $hasPositive = count(array_filter($scores, fn($s) => $s > 0)) > 0;
    echo "Has negative score: " . ($hasNegative ? 'yes' : 'no') . "\n";
    echo "Has positive score: " . ($hasPositive ? 'yes' : 'no') . "\n";

    // WeightedReRanker with IP metric — must handle negative scores
    $reranker = new ZVecWeightedReRanker(
        weights: ['v' => 1.0],
        topn: 3,
        metricType: ZVecSchema::METRIC_IP
    );
    $reranked = $reranker->rerank(['v' => $results]);
    echo "Reranked count: " . count($reranked) . "\n";

    // With IP normalization: (score - min) / range
    // negative gets 0, positive gets 1.0 (normalized)
    echo "Reranked order: " . implode(', ', array_map(fn($r) => $r->getPk(), $reranked)) . "\n";
    echo "Combined scores: " . implode(', ', array_map(fn($r) => round($r->getCombinedScore(), 4), $reranked)) . "\n";

    if (count($reranked) >= 3) {
        // 'positive' should have highest combined score (score=1.0, max)
        // 'negative' should have lowest (score=-0.5, min)
        $isCorrect = $reranked[0]->getPk() === 'positive'
            && $reranked[2]->getPk() === 'negative';
        echo "Negative score handling correct: " . ($isCorrect ? 'yes' : 'no') . "\n";
        echo "Positive doc combined score: " . round($reranked[0]->getCombinedScore(), 4) . "\n";
        echo "Negative doc combined score: " . round($reranked[2]->getCombinedScore(), 4) . "\n";

        // With proper normalization: positive=1.0, negative=0.0
        echo "Positive score should be 1.0: " . (abs($reranked[0]->getCombinedScore() - 1.0) < 0.001 ? 'yes' : 'no') . "\n";
        echo "Negative score should be 0.0: " . (abs($reranked[2]->getCombinedScore() - 0.0) < 0.001 ? 'yes' : 'no') . "\n";
    }

    $collection->close();
    echo "All negative score tests passed\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Query returned 3 results
Raw IP scores: %s
Has negative score: yes
Has positive score: yes
Reranked count: 3
Reranked order: positive, %s, negative
Combined scores: %s
Negative score handling correct: yes
Positive doc combined score: %f
Negative doc combined score: %f
Positive score should be 1.0: yes
Negative score should be 0.0: yes
All negative score tests passed
