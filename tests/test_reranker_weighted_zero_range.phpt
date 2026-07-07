--TEST--
WeightedReRanker: zero-range normalization guard (all scores identical)
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecWeightedReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/reranker_zero_range_' . uniqid();

try {
    // Create collection with METRIC_L2
    $schema = new ZVecSchema('test_zero_range');
    $schema->addVectorFp32('v', 4, ZVecSchema::METRIC_L2);
    $collection = ZVec::create($path, $schema);

    // Insert docs with identical vectors
    $docs = [];
    for ($i = 0; $i < 3; $i++) {
        $doc = new ZVecDoc("doc_$i");
        $doc->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0]);
        $docs[] = $doc;
    }
    $collection->insert(...$docs);
    $collection->optimize();

    // Query with the same vector — all docs score identical (L2 distance 0)
    $results = $collection->query('v', [1.0, 0.0, 0.0, 0.0], topk: 3);
    echo "Query returned " . count($results) . " results\n";

    // Verify all scores are identical
    $scores = array_map(fn($d) => $d->getScore(), $results);
    echo "All scores equal: " . (count(array_unique($scores)) === 1 ? 'yes' : 'no') . "\n";

    // WeightedReRanker with zero range — should not divide by zero
    $reranker = new ZVecWeightedReRanker(
        weights: ['v' => 1.0],
        topn: 3,
        metricType: ZVecSchema::METRIC_L2
    );
    $rerankedResults = $reranker->rerank(['v' => $results]);
    echo "Reranked count: " . count($rerankedResults) . "\n";

    // Combined scores should all be 0 (since all scores equal, range=0, guard sets range to 1.0)
    $first = $rerankedResults[0];
    echo "Combined score (zero range): " . $first->getCombinedScore() . "\n";

    $collection->close();
    echo "All zero-range tests passed\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Query returned 3 results
All scores equal: yes
Reranked count: 3
Combined score (zero range): 0
All zero-range tests passed
