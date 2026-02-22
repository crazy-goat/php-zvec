<?php

declare(strict_types=1);

/**
 * Example: Query by Document ID
 * 
 * This example demonstrates how to find similar documents by referencing
 * an existing document's embedding rather than providing an explicit vector.
 * 
 * Usage: php php/example_query_by_id.php
 */

require_once __DIR__ . '/ZVec.php';

// Initialize with console logging
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/example_query_by_id_' . uniqid();

try {
    echo "=== Example: Query by Document ID ===\n\n";

    // Create schema with a vector field
    $schema = new ZVecSchema('products');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('product_id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: true, withInvertIndex: true)
        ->addString('category', nullable: true, withInvertIndex: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $collection = ZVec::create($path, $schema);
    
    // Create HNSW index for fast similarity search
    $collection->createHnswIndex(
        fieldName: 'embedding',
        metricType: ZVecSchema::METRIC_IP,
        m: 16,
        efConstruction: 200
    );

    // Insert sample products with embeddings
    $products = [
        ['prod_001', 1, 'iPhone 15 Pro', 'smartphone', [1.0, 0.8, 0.2, 0.1]],
        ['prod_002', 2, 'Samsung Galaxy S24', 'smartphone', [0.9, 0.7, 0.3, 0.2]],
        ['prod_003', 3, 'Google Pixel 8', 'smartphone', [0.85, 0.75, 0.25, 0.15]],
        ['prod_004', 4, 'MacBook Pro M3', 'laptop', [0.3, 0.9, 0.8, 0.7]],
        ['prod_005', 5, 'Dell XPS 15', 'laptop', [0.25, 0.85, 0.75, 0.65]],
        ['prod_006', 6, 'iPad Pro', 'tablet', [0.6, 0.5, 0.4, 0.3]],
    ];

    foreach ($products as $p) {
        $doc = new ZVecDoc($p[0]);
        $doc->setInt64('product_id', $p[1])
            ->setString('name', $p[2])
            ->setString('category', $p[3])
            ->setVectorFp32('embedding', $p[4]);
        $collection->insert($doc);
    }
    
    echo "Inserted " . count($products) . " products\n\n";

    $collection->optimize();
    echo "Collection optimized\n\n";

    // Scenario 1: Find similar products to iPhone 15 Pro
    echo "Scenario 1: Find products similar to 'iPhone 15 Pro'\n";
    echo "--------------------------------------------------------\n";
    
    $similar = $collection->queryById(
        fieldName: 'embedding',
        docId: 'prod_001',  // iPhone 15 Pro
        topk: 5
    );

    echo "Query: Find similar to 'iPhone 15 Pro' (prod_001)\n";
    echo "Results:\n";
    foreach ($similar as $i => $doc) {
        $name = $doc->getString('name');
        $category = $doc->getString('category');
        echo sprintf("  %d. %s (%s) - score: %.4f\n", 
            $i + 1, $name, $category, $doc->getScore());
    }
    echo "\n";

    // Scenario 2: Find similar laptops (filtered by category)
    echo "Scenario 2: Find laptops similar to MacBook Pro\n";
    echo "--------------------------------------------------------\n";
    
    $similarLaptops = $collection->queryById(
        fieldName: 'embedding',
        docId: 'prod_004',  // MacBook Pro
        topk: 10,
        filter: "category = 'laptop'"  // Only show laptops
    );

    echo "Query: Find laptops similar to 'MacBook Pro M3' (prod_004)\n";
    echo "Results:\n";
    foreach ($similarLaptops as $i => $doc) {
        $name = $doc->getString('name');
        echo sprintf("  %d. %s - score: %.4f\n", 
            $i + 1, $name, $doc->getScore());
    }
    echo "\n";

    // Scenario 3: Compare with regular query (using explicit vector)
    echo "Scenario 3: Compare queryById with regular query\n";
    echo "--------------------------------------------------------\n";
    
    // First, fetch the iPhone document
    $iphoneDocs = $collection->fetch('prod_001');
    $iphoneVector = $iphoneDocs[0]->getVectorFp32('embedding');
    
    // Query using the explicit vector
    $resultsVector = $collection->query(
        fieldName: 'embedding',
        queryVector: $iphoneVector,
        topk: 3
    );
    
    // Query using queryById (same thing, but simpler)
    $resultsById = $collection->queryById(
        fieldName: 'embedding',
        docId: 'prod_001',
        topk: 3
    );
    
    echo "Regular query (explicit vector) results:\n";
    foreach ($resultsVector as $doc) {
        echo "  - " . $doc->getString('name') . "\n";
    }
    
    echo "\nqueryById results:\n";
    foreach ($resultsById as $doc) {
        echo "  - " . $doc->getString('name') . "\n";
    }
    
    echo "\nBoth methods return identical results!\n\n";

    // Scenario 4: Error handling
    echo "Scenario 4: Error handling\n";
    echo "--------------------------------------------------------\n";
    
    try {
        $collection->queryById('embedding', 'nonexistent_product', topk: 5);
    } catch (ZVecException $e) {
        echo "Error caught: " . $e->getMessage() . "\n";
    }

    $collection->close();
    echo "\n=== Example completed successfully ===\n";
    
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
