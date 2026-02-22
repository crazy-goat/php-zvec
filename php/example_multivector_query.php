<?php

declare(strict_types=1);

require_once __DIR__ . '/ZVec.php';
require_once __DIR__ . '/ZVecRrfReRanker.php';
require_once __DIR__ . '/ZVecWeightedReRanker.php';

/**
 * Multi-Vector Query Demo - Hybrid Search with Multiple Vector Fields
 *
 * This example demonstrates queryMulti() for searching across multiple vector
 * fields simultaneously and fusing results using rerankers.
 *
 * Use cases:
 * - Dense + sparse vector fusion (semantic + keyword search)
 * - Multi-modal search (text + image embeddings)
 * - Cross-lingual search (embeddings in different languages)
 * - Multi-representation search (title + body + abstract embeddings)
 */

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/example_multivector_' . uniqid();

echo "=== Multi-Vector Query Demo: Hybrid Search ===\n\n";

try {
    // Scenario: Article search with title and content embeddings
    // Users can search by semantic meaning OR keyword matching
    $schema = new ZVecSchema('articles');
    $schema->addString('title', withInvertIndex: true)
           ->addString('category', withInvertIndex: true)
           ->addVectorFp32('title_embedding', 4, ZVecSchema::METRIC_IP)   // Semantic title search
           ->addVectorFp32('content_embedding', 4, ZVecSchema::METRIC_IP); // Semantic content search

    $collection = ZVec::create($path, $schema);
    echo "1. Created collection with dual vector fields:\n";
    echo "   - title_embedding: for title semantic search\n";
    echo "   - content_embedding: for content semantic search\n\n";

    // Insert articles with different title vs content characteristics
    $articles = [
        (new ZVecDoc('art1'))
            ->setString('title', 'Introduction to Vector Databases')
            ->setString('category', 'Database')
            ->setVectorFp32('title_embedding', [0.95, 0.85, 0.75, 0.65])      // Strong title match
            ->setVectorFp32('content_embedding', [0.3, 0.2, 0.1, 0.1]),      // Weak content match

        (new ZVecDoc('art2'))
            ->setString('title', 'PHP Programming Basics')
            ->setString('category', 'Programming')
            ->setVectorFp32('title_embedding', [0.2, 0.1, 0.1, 0.1])          // Weak title match
            ->setVectorFp32('content_embedding', [0.95, 0.85, 0.75, 0.65]),   // Strong content match

        (new ZVecDoc('art3'))
            ->setString('title', 'Vector Search in PHP Applications')
            ->setString('category', 'Tutorial')
            ->setVectorFp32('title_embedding', [0.85, 0.75, 0.65, 0.55])      // Good title match
            ->setVectorFp32('content_embedding', [0.75, 0.65, 0.55, 0.45]),   // Good content match

        (new ZVecDoc('art4'))
            ->setString('title', 'Machine Learning Fundamentals')
            ->setString('category', 'AI')
            ->setVectorFp32('title_embedding', [0.4, 0.3, 0.2, 0.1])
            ->setVectorFp32('content_embedding', [0.4, 0.3, 0.2, 0.1]),
    ];

    $collection->insert(...$articles);
    $collection->optimize();
    echo "2. Inserted " . count($articles) . " articles\n\n";

    // Query: looking for articles about "PHP vector databases"
    // We'll search both title and content embeddings
    $titleQueryVector = [0.9, 0.8, 0.7, 0.6];    // "vector database PHP" in title space
    $contentQueryVector = [0.8, 0.7, 0.6, 0.5];   // "vector database PHP" in content space

    // Scenario 1: Single field search (baseline)
    echo "3. Single-field search comparison:\n";
    $titleResults = $collection->query('title_embedding', $titleQueryVector, topk: 3);
    $contentResults = $collection->query('content_embedding', $contentQueryVector, topk: 3);

    echo "   Title-only search:\n";
    foreach ($titleResults as $i => $doc) {
        echo "     " . ($i + 1) . ". {$doc->getPk()}: {$doc->getString('title')} (score: " . round($doc->getScore(), 4) . ")\n";
    }

    echo "   Content-only search:\n";
    foreach ($contentResults as $i => $doc) {
        echo "     " . ($i + 1) . ". {$doc->getPk()}: {$doc->getString('title')} (score: " . round($doc->getScore(), 4) . ")\n";
    }
    echo "\n";

    // Scenario 2: Multi-vector search with RRF reranker
    echo "4. Multi-vector search with RRF reranker:\n";
    echo "   - Combines title and content search results\n";
    echo "   - Uses Reciprocal Rank Fusion (RRF) algorithm\n\n";

    $titleVq = new ZVecVectorQuery('title_embedding', $titleQueryVector);
    $contentVq = new ZVecVectorQuery('content_embedding', $contentQueryVector);

    $rrfReranker = new ZVecRrfReRanker(topn: 3, rankConstant: 60);
    $multiResults = $collection->queryMulti(
        vectorQueries: [$titleVq, $contentVq],
        reranker: $rrfReranker,
        topk: 3
    );

    echo "   Combined results (top 3):\n";
    foreach ($multiResults as $i => $result) {
        $title = $result->doc->getString('title');
        $ranks = $result->sourceRanks;
        echo "   " . ($i + 1) . ". {$result->getPk()}: {$title}\n";
        echo "      Combined RRF score: " . round($result->combinedScore, 6) . "\n";
        echo "      Source ranks: title=" . ($ranks['title_embedding'] ?? '-') .
             ", content=" . ($ranks['content_embedding'] ?? '-') . "\n\n";
    }

    // Scenario 3: Multi-vector search with different weights
    echo "5. Multi-vector search with Weighted reranker:\n";
    echo "   - Title weight: 0.6 (more important)\n";
    echo "   - Content weight: 0.4\n\n";

    $weightedReranker = new ZVecWeightedReRanker(
        topn: 3,
        metricType: ZVecSchema::METRIC_IP,
        weights: ['title_embedding' => 0.6, 'content_embedding' => 0.4]
    );

    $weightedResults = $collection->queryMulti(
        vectorQueries: [$titleVq, $contentVq],
        reranker: $weightedReranker,
        topk: 3
    );

    echo "   Weighted results (top 3):\n";
    foreach ($weightedResults as $i => $result) {
        $title = $result->doc->getString('title');
        $scores = $result->sourceScores;
        echo "   " . ($i + 1) . ". {$result->getPk()}: {$title}\n";
        echo "      Combined weighted score: " . round($result->combinedScore, 4) . "\n";
        echo "      Source scores: title=" . round($scores['title_embedding'] ?? 0, 4) .
             ", content=" . round($scores['content_embedding'] ?? 0, 4) . "\n\n";
    }

    // Scenario 4: Multi-vector search with filter
    echo "6. Multi-vector search with filter:\n";
    echo "   - Filter: category = 'Tutorial'\n\n";

    $filteredResults = $collection->queryMulti(
        vectorQueries: [$titleVq, $contentVq],
        reranker: $rrfReranker,
        topk: 3,
        filter: "category = 'Tutorial'"
    );

    echo "   Filtered results:\n";
    if (empty($filteredResults)) {
        echo "   (No results matching filter)\n";
    } else {
        foreach ($filteredResults as $i => $result) {
            $title = $result->doc->getString('title');
            $category = $result->doc->getString('category');
            echo "   " . ($i + 1) . ". {$result->getPk()}: {$title} [{$category}]\n";
        }
    }
    echo "\n";

    // Scenario 5: Multi-vector search with different query parameters per field
    echo "7. Multi-vector with field-specific query parameters:\n";
    echo "   - Title: uses HNSW with ef=100\n";
    echo "   - Content: uses HNSW with default ef=200\n\n";

    $titleVqHnsw = (new ZVecVectorQuery('title_embedding', $titleQueryVector))
        ->setHnswParams(ef: 100);
    $contentVqDefault = new ZVecVectorQuery('content_embedding', $contentQueryVector);

    $paramResults = $collection->queryMulti(
        vectorQueries: [$titleVqHnsw, $contentVqDefault],
        reranker: $rrfReranker,
        topk: 3
    );

    echo "   Results with mixed HNSW params:\n";
    foreach ($paramResults as $i => $result) {
        echo "   " . ($i + 1) . ". {$result->getPk()}: {$result->doc->getString('title')}\n";
    }
    echo "\n";

    // Summary
    echo "=== Key Takeaways ===\n";
    echo "• queryMulti() enables searching multiple vector fields simultaneously\n";
    echo "• RRF reranker is simpler (rank-based, no normalization needed)\n";
    echo "• Weighted reranker allows fine-tuning field importance\n";
    echo "• Each ZVecVectorQuery can have its own query parameters\n";
    echo "• Filters apply to all vector queries in the multi-search\n";
    echo "• Results are always ZVecRerankedDoc objects with combined scores\n";

    $collection->close();
    echo "\n✓ Demo completed successfully!\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}
