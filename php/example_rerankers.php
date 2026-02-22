<?php

declare(strict_types=1);

require_once __DIR__ . '/ZVec.php';
require_once __DIR__ . '/ZVecRrfReRanker.php';
require_once __DIR__ . '/ZVecWeightedReRanker.php';

/**
 * Rerankers Demo - Multi-Vector Search Result Fusion
 * 
 * This example demonstrates how to combine results from multiple vector queries
 * using Reciprocal Rank Fusion (RRF) and Weighted scoring.
 */

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/example_rerankers_' . uniqid();

echo "=== Rerankers Demo: Multi-Vector Result Fusion ===\n\n";

try {
    // Create a collection with two vector fields (dense and sparse-like)
    $schema = new ZVecSchema('demo_collection');
    $schema->addInt64('id', withInvertIndex: true)
           ->addString('title', withInvertIndex: true)
           ->addVectorFp32('semantic_embedding', 4, ZVecSchema::METRIC_IP)
           ->addVectorFp32('keyword_embedding', 4, ZVecSchema::METRIC_IP);
    
    $collection = ZVec::create($path, $schema);
    echo "1. Created collection with two vector fields:\n";
    echo "   - semantic_embedding (for semantic similarity)\n";
    echo "   - keyword_embedding (for keyword matching)\n\n";
    
    // Add documents with different characteristics
    $docs = [
        (new ZVecDoc('doc1'))
            ->setInt64('id', 1)
            ->setString('title', 'PHP Vector Database Guide')
            ->setVectorFp32('semantic_embedding', [0.9, 0.8, 0.7, 0.6])
            ->setVectorFp32('keyword_embedding', [0.3, 0.2, 0.1, 0.1]),
        
        (new ZVecDoc('doc2'))
            ->setInt64('id', 2)
            ->setString('title', 'Machine Learning Basics')
            ->setVectorFp32('semantic_embedding', [0.7, 0.9, 0.6, 0.8])
            ->setVectorFp32('keyword_embedding', [0.8, 0.7, 0.6, 0.5]),
        
        (new ZVecDoc('doc3'))
            ->setInt64('id', 3)
            ->setString('title', 'Introduction to PHP')
            ->setVectorFp32('semantic_embedding', [0.5, 0.4, 0.3, 0.2])
            ->setVectorFp32('keyword_embedding', [0.9, 0.8, 0.7, 0.6]),
        
        (new ZVecDoc('doc4'))
            ->setInt64('id', 4)
            ->setString('title', 'Advanced Vector Search')
            ->setVectorFp32('semantic_embedding', [0.8, 0.7, 0.9, 0.6])
            ->setVectorFp32('keyword_embedding', [0.4, 0.3, 0.2, 0.1]),
    ];
    
    $collection->insert(...$docs);
    $collection->optimize();
    echo "2. Inserted " . count($docs) . " documents\n\n";
    
    // Query both vector fields separately
    $semanticVector = [0.8, 0.8, 0.7, 0.7]; // Looking for semantic similarity to "vector databases"
    $keywordVector = [0.8, 0.7, 0.6, 0.5]; // Looking for keyword matches
    
    $semanticResults = $collection->query('semantic_embedding', $semanticVector, topk: 4);
    $keywordResults = $collection->query('keyword_embedding', $keywordVector, topk: 4);
    
    echo "3. Query results from individual fields:\n";
    echo "   Semantic field:\n";
    foreach ($semanticResults as $i => $doc) {
        echo "     " . ($i + 1) . ". {$doc->getPk()}: score={$doc->getScore()}\n";
    }
    echo "   Keyword field:\n";
    foreach ($keywordResults as $i => $doc) {
        echo "     " . ($i + 1) . ". {$doc->getPk()}: score={$doc->getScore()}\n";
    }
    echo "\n";
    
    // Prepare query results for reranking
    $queryResults = [
        'semantic_embedding' => $semanticResults,
        'keyword_embedding' => $keywordResults,
    ];
    
    // 1. RRF ReRanker
    echo "4. RRF (Reciprocal Rank Fusion) ReRanker:\n";
    echo "   - Formula: Score = 1 / (k + rank), k=60\n";
    echo "   - No score normalization needed, works on rankings only\n\n";
    
    $rrfReranker = new ZVecRrfReRanker(topn: 3, rankConstant: 60);
    $rrfResults = $rrfReranker->rerank($queryResults);
    
    echo "   Combined results (top 3):\n";
    foreach ($rrfResults as $i => $result) {
        $title = $result->doc->getString('title');
        $ranks = $result->sourceRanks;
        echo "   " . ($i + 1) . ". {$result->getPk()}: {$title}\n";
        echo "      Combined score: " . round($result->combinedScore, 4) . "\n";
        echo "      Source ranks: semantic=" . ($ranks['semantic_embedding'] ?? 'N/A') . 
             ", keyword=" . ($ranks['keyword_embedding'] ?? 'N/A') . "\n\n";
    }
    
    // 2. Weighted ReRanker
    echo "5. Weighted ReRanker:\n";
    echo "   - Semantic weight: 0.7 (more important)\n";
    echo "   - Keyword weight: 0.3\n";
    echo "   - Metric: IP (Inner Product)\n\n";
    
    $weightedReranker = new ZVecWeightedReRanker(
        topn: 3,
        metricType: ZVecSchema::METRIC_IP,
        weights: ['semantic_embedding' => 0.7, 'keyword_embedding' => 0.3]
    );
    $weightedResults = $weightedReranker->rerank($queryResults);
    
    echo "   Combined results (top 3):\n";
    foreach ($weightedResults as $i => $result) {
        $title = $result->doc->getString('title');
        $scores = $result->sourceScores;
        echo "   " . ($i + 1) . ". {$result->getPk()}: {$title}\n";
        echo "      Combined score: " . round($result->combinedScore, 4) . "\n";
        echo "      Source scores: semantic=" . round($scores['semantic_embedding'] ?? 0, 4) . 
             ", keyword=" . round($scores['keyword_embedding'] ?? 0, 4) . "\n\n";
    }
    
    // 3. Single field reranking (edge case)
    echo "6. Single Field Reranking (edge case):\n";
    $singleReranker = new ZVecRrfReRanker(topn: 2);
    $singleResults = $singleReranker->rerank(['semantic_embedding' => $semanticResults]);
    echo "   RRF on single field returns top 2:\n";
    foreach ($singleResults as $i => $result) {
        echo "   " . ($i + 1) . ". {$result->getPk()}: score=" . 
             round($result->combinedScore, 4) . "\n";
    }
    echo "\n";
    
    // Key takeaways
    echo "=== Key Takeaways ===\n";
    echo "• RRF is simpler - no score normalization needed\n";
    echo "• Weighted allows fine-tuning importance of each field\n";
    echo "• Both return ZVecRerankedDoc with combined scores and source info\n";
    echo "• Useful for hybrid search (dense + sparse, or multiple embeddings)\n";
    
    $collection->close();
    echo "\nDone! Collection destroyed.\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
