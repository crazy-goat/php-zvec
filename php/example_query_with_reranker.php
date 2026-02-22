<?php

declare(strict_types=1);

require_once __DIR__ . '/ZVec.php';
require_once __DIR__ . '/ZVecRrfReRanker.php';
require_once __DIR__ . '/ZVecWeightedReRanker.php';

/**
 * Query with Reranker Parameter - Two-Stage Retrieval Demo
 * 
 * This example demonstrates using the reranker parameter in query()
 * for two-stage retrieval: fetch many candidates, then rerank to top results.
 */

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/example_query_reranker_' . uniqid();

echo "=== Query with Reranker Parameter: Two-Stage Retrieval ===\n\n";

try {
    // Create collection with vector field
    $schema = new ZVecSchema('demo_collection');
    $schema->addString('title', withInvertIndex: true)
           ->addVectorFp32('embedding', 4, ZVecSchema::METRIC_IP);
    
    $collection = ZVec::create($path, $schema);
    echo "1. Created collection with 'embedding' vector field\n\n";
    
    // Insert test documents with various similarity to query vector
    $docs = [
        (new ZVecDoc('doc1'))
            ->setString('title', 'Machine Learning Guide')
            ->setVectorFp32('embedding', [0.9, 0.8, 0.7, 0.6]),
        
        (new ZVecDoc('doc2'))
            ->setString('title', 'Deep Learning Tutorial')
            ->setVectorFp32('embedding', [0.85, 0.75, 0.65, 0.55]),
        
        (new ZVecDoc('doc3'))
            ->setString('title', 'PHP Programming')
            ->setVectorFp32('embedding', [0.2, 0.3, 0.4, 0.5]),
        
        (new ZVecDoc('doc4'))
            ->setString('title', 'Vector Databases')
            ->setVectorFp32('embedding', [0.88, 0.78, 0.68, 0.58]),
        
        (new ZVecDoc('doc5'))
            ->setString('title', 'AI Research Paper')
            ->setVectorFp32('embedding', [0.82, 0.72, 0.62, 0.52]),
        
        (new ZVecDoc('doc6'))
            ->setString('title', 'Web Development')
            ->setVectorFp32('embedding', [0.3, 0.4, 0.5, 0.6]),
        
        (new ZVecDoc('doc7'))
            ->setString('title', 'Neural Networks')
            ->setVectorFp32('embedding', [0.87, 0.77, 0.67, 0.57]),
        
        (new ZVecDoc('doc8'))
            ->setString('title', 'Data Science')
            ->setVectorFp32('embedding', [0.8, 0.7, 0.6, 0.5]),
    ];
    
    $collection->insert(...$docs);
    $collection->optimize();
    echo "2. Inserted " . count($docs) . " documents\n\n";
    
    // Query vector looking for ML/AI related content
    $queryVector = [0.9, 0.8, 0.7, 0.6];
    
    // Scenario 1: Standard query without reranker
    echo "3. Standard query (no reranker):\n";
    $results = $collection->query('embedding', $queryVector, topk: 3);
    echo "   Returned " . count($results) . " ZVecDoc objects:\n";
    foreach ($results as $i => $doc) {
        echo "   " . ($i + 1) . ". {$doc->getPk()}: {$doc->getString('title')} (score: " . round($doc->getScore(), 4) . ")\n";
    }
    echo "\n";
    
    // Scenario 2: Two-stage retrieval with RRF reranker
    echo "4. Two-stage retrieval with RRF reranker:\n";
    echo "   - Fetches 100 candidates (max(topk*2, 100))\n";
    echo "   - Reranks to top 3 using RRF algorithm\n";
    
    $rrfReranker = new ZVecRrfReRanker(topn: 3, rankConstant: 60);
    $results = $collection->query(
        'embedding',
        $queryVector,
        topk: 3,
        reranker: $rrfReranker
    );
    
    echo "   Returned " . count($results) . " ZVecRerankedDoc objects:\n";
    foreach ($results as $i => $doc) {
        echo "   " . ($i + 1) . ". {$doc->getPk()}: " . $doc->doc->getString('title') . "\n";
        echo "      Combined score: " . round($doc->combinedScore, 4) . "\n";
        echo "      Source rank: " . ($doc->sourceRanks['embedding'] ?? 'N/A') . "\n";
    }
    echo "\n";
    
    // Scenario 3: Two-stage with Weighted reranker
    echo "5. Two-stage retrieval with Weighted reranker:\n";
    echo "   - Uses score normalization and weighted combination\n";
    
    $weightedReranker = new ZVecWeightedReRanker(
        topn: 3,
        metricType: ZVecSchema::METRIC_IP,
        weights: ['embedding' => 1.0]
    );
    
    $results = $collection->query(
        'embedding',
        $queryVector,
        topk: 3,
        reranker: $weightedReranker
    );
    
    echo "   Returned " . count($results) . " ZVecRerankedDoc objects:\n";
    foreach ($results as $i => $doc) {
        echo "   " . ($i + 1) . ". {$doc->getPk()}: " . $doc->doc->getString('title') . "\n";
        echo "      Combined score: " . round($doc->combinedScore, 4) . "\n";
        echo "      Original score: " . round($doc->sourceScores['embedding'], 4) . "\n";
    }
    echo "\n";
    
    // Scenario 4: Using ZVecVectorQuery with reranker
    echo "6. Using ZVecVectorQuery object with reranker:\n";
    $vq = new ZVecVectorQuery('embedding', $queryVector);
    $vq->setHnswParams(ef: 100);
    
    $results = $collection->query($vq, topk: 3, reranker: $rrfReranker);
    echo "   Returned " . count($results) . " results using VectorQuery + RRF\n";
    foreach ($results as $i => $doc) {
        echo "   " . ($i + 1) . ". {$doc->getPk()}: " . $doc->doc->getString('title') . "\n";
    }
    echo "\n";
    
    $collection->close();
    echo "✓ All scenarios completed successfully!\n";
    
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
