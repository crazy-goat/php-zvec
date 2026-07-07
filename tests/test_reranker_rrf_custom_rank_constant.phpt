--TEST--
RRF ReRanker: custom rank constant changes combined scores
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecRrfReRanker.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/reranker_rrf_k_' . uniqid();

try {
    // Create collection
    $schema = new ZVecSchema('test_rrf_k');
    $schema->addVectorFp32('v1', 4, ZVecSchema::METRIC_IP)
           ->addVectorFp32('v2', 4, ZVecSchema::METRIC_IP);
    $collection = ZVec::create($path, $schema);

    // Insert docs
    $docs = [
        (new ZVecDoc('doc1'))->setVectorFp32('v1', [0.9, 0.0, 0.0, 0.0])->setVectorFp32('v2', [0.3, 0.0, 0.0, 0.0]),
        (new ZVecDoc('doc2'))->setVectorFp32('v1', [0.8, 0.0, 0.0, 0.0])->setVectorFp32('v2', [0.2, 0.0, 0.0, 0.0]),
        (new ZVecDoc('doc3'))->setVectorFp32('v1', [0.1, 0.0, 0.0, 0.0])->setVectorFp32('v2', [0.9, 0.0, 0.0, 0.0]),
    ];
    $collection->insert(...$docs);
    $collection->optimize();

    $queryVector = [1.0, 0.0, 0.0, 0.0];
    $r1 = $collection->query('v1', $queryVector, topk: 3);
    $r2 = $collection->query('v2', $queryVector, topk: 3);
    $queryResults = ['v1' => $r1, 'v2' => $r2];

    // Default rank constant (60)
    $defaultReranker = new ZVecRrfReRanker(topn: 3, rankConstant: 60);
    $defaultResults = $defaultReranker->rerank($queryResults);

    // Custom rank constant (1) — higher RRF scores
    $customReranker1 = new ZVecRrfReRanker(topn: 3, rankConstant: 1);
    $customResults1 = $customReranker1->rerank($queryResults);

    // Custom rank constant (100) — lower RRF scores
    $customReranker100 = new ZVecRrfReRanker(topn: 3, rankConstant: 100);
    $customResults100 = $customReranker100->rerank($queryResults);

    echo "Default (k=60) top-3 combined scores:\n";
    foreach ($defaultResults as $r) {
        echo "  {$r->getPk()}: " . round($r->getCombinedScore(), 6) . "\n";
    }

    echo "Custom (k=1) top-3 combined scores:\n";
    foreach ($customResults1 as $r) {
        echo "  {$r->getPk()}: " . round($r->getCombinedScore(), 6) . "\n";
    }

    echo "Custom (k=100) top-3 combined scores:\n";
    foreach ($customResults100 as $r) {
        echo "  {$r->getPk()}: " . round($r->getCombinedScore(), 6) . "\n";
    }

    // Verify k=1 gives highest scores, k=100 gives lowest
    if (count($defaultResults) > 0 && count($customResults1) > 0 && count($customResults100) > 0) {
        $scoreK1 = $customResults1[0]->getCombinedScore();
        $scoreK60 = $defaultResults[0]->getCombinedScore();
        $scoreK100 = $customResults100[0]->getCombinedScore();
        echo "Score order (k=1 > k=60 > k=100): " 
            . ($scoreK1 > $scoreK60 && $scoreK60 > $scoreK100 ? 'yes' : 'no') . "\n";
    }

    // Verify getter/setter for rankConstant
    $reranker = new ZVecRrfReRanker(topn: 3);
    echo "Default rankConstant: " . $reranker->getRankConstant() . "\n";
    $reranker->setRankConstant(42);
    echo "After setRankConstant(42): " . $reranker->getRankConstant() . "\n";

    // getTopn getter/setter
    echo "Default topn: " . $reranker->getTopn() . "\n";
    $reranker->setTopn(5);
    echo "After setTopn(5): " . $reranker->getTopn() . "\n";

    // Verify zero rankConstant edge case — should still work (division by 1/(0+rank))
    $zeroK = new ZVecRrfReRanker(topn: 3, rankConstant: 0);
    $zeroResults = $zeroK->rerank($queryResults);
    echo "Zero rank constant results: " . count($zeroResults) . "\n";
    if (count($zeroResults) > 0) {
        // k=0 => score = 1/rank for each field
        // rank 1: 1/1 = 1.0, rank 2: 1/2 = 0.5
        echo "Zero k first combined score: " . round($zeroResults[0]->getCombinedScore(), 6) . "\n";
    }

    $collection->close();
    echo "All custom rank constant tests passed\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Default (k=60) top-3 combined scores:
  %s
  %s
  %s
Custom (k=1) top-3 combined scores:
  %s
  %s
  %s
Custom (k=100) top-3 combined scores:
  %s
  %s
  %s
Score order (k=1 > k=60 > k=100): yes
Default rankConstant: 60
After setRankConstant(42): 42
Default topn: 3
After setTopn(5): 5
Zero rank constant results: 3
Zero k first combined score: %f
All custom rank constant tests passed
