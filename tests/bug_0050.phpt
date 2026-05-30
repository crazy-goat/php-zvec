--TEST--
Bug 0050: WeightedReRanker PHP_FLOAT_MIN produces wrong normalization for negative IP scores
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecWeightedReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/bug_0050_' . uniqid();
try {
    // Create collection with IP metric
    $schema = new ZVecSchema('bug_0050');
    $schema->addVectorFp32('embedding', 4, ZVecSchema::METRIC_IP);
    $collection = ZVec::create($path, $schema);

    // Insert docs with varying scores (including negative for IP)
    $docs = [
        (new ZVecDoc('high_match'))
            ->setVectorFp32('embedding', [0.9, 0.8, 0.7, 0.6]),
        (new ZVecDoc('medium_match'))
            ->setVectorFp32('embedding', [0.5, 0.4, 0.3, 0.2]),
        (new ZVecDoc('negative_match'))
            ->setVectorFp32('embedding', [-0.1, -0.2, -0.3, -0.4]),
    ];
    $collection->insert(...$docs);
    $collection->optimize();

    // Test 1: IP metric with mixed (positive + negative) scores
    $reranker = new ZVecWeightedReRanker(
        topn: 5,
        metricType: ZVecSchema::METRIC_IP,
        weights: ['embedding' => 1.0]
    );

    $query = new ZVecVectorQuery('embedding', [0.9, 0.8, 0.7, 0.6]);
    $results = $collection->queryMulti(
        vectorQueries: [$query],
        reranker: $reranker,
        topk: 10
    );

    foreach ($results as $doc) {
        $score = $doc->getCombinedScore();
        if ($score < 0.0 || $score > 1.0) {
            echo "FAIL: Mixed-scores score {$score} out of [0, 1]\n";
            exit(1);
        }
    }

    $first = $results[0]->getPk();
    if ($first !== 'high_match') {
        echo "FAIL: Expected high_match first, got {$first}\n";
        exit(1);
    }
    echo "Bug 0050: Mixed IP scores normalize correctly\n";

    // Test 2: ALL-negative IP scores — the actual PHP_FLOAT_MIN bug scenario
    // Query with a vector that will produce negative IP against all docs
    $allNegQuery = new ZVecVectorQuery('embedding', [-0.5, -0.5, -0.5, -0.5]);
    $resultsAllNeg = $collection->queryMulti(
        vectorQueries: [$allNegQuery],
        reranker: $reranker,
        topk: 10
    );

    foreach ($resultsAllNeg as $doc) {
        $score = $doc->getCombinedScore();
        if ($score < 0.0 || $score > 1.0) {
            echo "FAIL: All-negative score {$score} out of [0, 1] for doc {$doc->getPk()}\n";
            exit(1);
        }
    }
    echo "Bug 0050: All-negative IP scores normalize correctly (PHP_FLOAT_MIN fix)\n";

    // Test 3: Verify L2 metric with ZVecSchema::METRIC_L2 constant
    $collection2Path = __DIR__ . '/../test_dbs/bug_0050_l2_' . uniqid();
    try {
        $schemaL2 = new ZVecSchema('bug_0050_l2');
        $schemaL2->addVectorFp32('embedding', 4, ZVecSchema::METRIC_L2);
        $c2 = ZVec::create($collection2Path, $schemaL2);
        $c2->insert(...$docs);
        $c2->optimize();

        $rerankerL2 = new ZVecWeightedReRanker(
            topn: 5,
            metricType: ZVecSchema::METRIC_L2,
            weights: ['embedding' => 1.0]
        );

        $results2 = $c2->queryMulti(
            vectorQueries: [new ZVecVectorQuery('embedding', [0.9, 0.8, 0.7, 0.6])],
            reranker: $rerankerL2,
            topk: 10
        );

        foreach ($results2 as $doc) {
            $score = $doc->getCombinedScore();
            if ($score < 0.0 || $score > 1.0) {
                echo "FAIL: L2 Score {$score} out of [0, 1] range\n";
                exit(1);
            }
        }
        echo "Bug 0050: L2 metric normalization correct with METRIC_L2 constant\n";
        $c2->close();
    } finally {
        exec("rm -rf " . escapeshellarg($collection2Path));
    }

    $collection->close();
    echo "PASS\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Bug 0050: Mixed IP scores normalize correctly
Bug 0050: All-negative IP scores normalize correctly (PHP_FLOAT_MIN fix)
Bug 0050: L2 metric normalization correct with METRIC_L2 constant
PASS
