--TEST--
Rerankers: RRF and Weighted reranker functionality
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
require_once __DIR__ . '/../php/ZVecRrfReRanker.php';
require_once __DIR__ . '/../php/ZVecWeightedReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/reranker_' . uniqid();

try {
    // Create collection with two vector fields
    $schema = new ZVecSchema('test_reranker');
    $schema->addInt64('id', withInvertIndex: true)
           ->addVectorFp32('dense_embedding', 4, ZVecSchema::METRIC_IP)
           ->addVectorFp32('sparse_embedding', 4, ZVecSchema::METRIC_IP);
    
    $collection = ZVec::create($path, $schema);
    
    // Add some test documents
    $docs = [];
    for ($i = 0; $i < 5; $i++) {
        $docs[] = (new ZVecDoc("doc_$i"))
            ->setInt64('id', $i)
            ->setVectorFp32('dense_embedding', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i])
            ->setVectorFp32('sparse_embedding', [0.5 * $i, 0.4 * $i, 0.3 * $i, 0.2 * $i]);
    }
    
    $collection->insert(...$docs);
    $collection->optimize();
    
    // Query both fields
    $denseResults = $collection->query('dense_embedding', [0.1, 0.2, 0.3, 0.4], topk: 5);
    $sparseResults = $collection->query('sparse_embedding', [0.5, 0.4, 0.3, 0.2], topk: 5);
    
    echo "Dense results count: " . count($denseResults) . "\n";
    echo "Sparse results count: " . count($sparseResults) . "\n";
    
    // Test RRF reranker
    $queryResults = [
        'dense_embedding' => $denseResults,
        'sparse_embedding' => $sparseResults,
    ];
    
    $rrfReranker = new ZVecRrfReRanker(topn: 3, rankConstant: 60);
    $rrfResults = $rrfReranker->rerank($queryResults);
    
    echo "RRF reranked count: " . count($rrfResults) . "\n";
    
    // Verify RRF results structure (don't check exact pk as it depends on search algorithm)
    if (count($rrfResults) > 0) {
        $first = $rrfResults[0];
        echo "RRF first result has combinedScore: " . ($first->combinedScore > 0 ? 'yes' : 'no') . "\n";
        echo "RRF first result has sourceRanks: " . (count($first->sourceRanks) > 0 ? 'yes' : 'no') . "\n";
        echo "RRF first result has pk: " . (strlen($first->getPk()) > 0 ? 'yes' : 'no') . "\n";
    }
    
    // Test Weighted reranker
    $weightedReranker = new ZVecWeightedReRanker(
        topn: 3,
        metricType: ZVecSchema::METRIC_IP,
        weights: ['dense_embedding' => 0.6, 'sparse_embedding' => 0.4]
    );
    $weightedResults = $weightedReranker->rerank($queryResults);
    
    echo "Weighted reranked count: " . count($weightedResults) . "\n";
    
    if (count($weightedResults) > 0) {
        $first = $weightedResults[0];
        echo "Weighted first result has combinedScore: " . ($first->combinedScore >= 0 ? 'yes' : 'no') . "\n";
        echo "Weighted first result has sourceScores: " . (count($first->sourceScores) > 0 ? 'yes' : 'no') . "\n";
    }
    
    // Test edge cases
    $emptyReranker = new ZVecRrfReRanker(topn: 5);
    $emptyResults = $emptyReranker->rerank([]);
    echo "Empty query results: " . count($emptyResults) . "\n";
    
    // Test single field reranking
    $singleFieldReranker = new ZVecRrfReRanker(topn: 3);
    $singleResults = $singleFieldReranker->rerank(['dense_embedding' => $denseResults]);
    echo "Single field reranked count: " . count($singleResults) . "\n";
    
    echo "All reranker tests passed\n";
    
    $collection->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Dense results count: 5
Sparse results count: 5
RRF reranked count: 3
RRF first result has combinedScore: yes
RRF first result has sourceRanks: yes
RRF first result has pk: yes
Weighted reranked count: 3
Weighted first result has combinedScore: yes
Weighted first result has sourceScores: yes
Empty query results: 0
Single field reranked count: 3
All reranker tests passed
