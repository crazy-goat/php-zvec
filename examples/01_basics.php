<?php

declare(strict_types=1);

/**
 * Example 1: Basics - Creating a collection and adding documents
 * 
 * Demonstrates:
 * - ZVec initialization
 * - Collection schema creation
 * - Adding documents (single and batch)
 * - Index optimization
 * - Basic data retrieval
 */

require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/example_01_' . uniqid();

try {
    echo "=== Example 1: Basics ===\n\n";

    // Initialization
    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
    echo "[1] ZVec initialized\n";

    // Schema creation
    $schema = new ZVecSchema('products');
    $schema
        ->addString('name', nullable: false)
        ->addFloat('price', nullable: true)
        ->addVectorFp32('vector', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    echo "[2] Schema created (name, price, vector[4])\n";

    // Collection creation
    $collection = ZVec::create($path, $schema);
    echo "[3] Collection created at: {$path}\n";

    // Adding a single document
    $laptop = new ZVecDoc('prod_001');
    $laptop
        ->setString('name', 'Laptop Dell')
        ->setFloat('price', 3999.99)
        ->setVectorFp32('vector', [0.9, 0.1, 0.2, 0.3]);
    $collection->insert($laptop);
    echo "[4] Added 1 document (Laptop)\n";

    // Adding multiple documents (batch)
    $tablet = new ZVecDoc('prod_002');
    $tablet->setString('name', 'iPad Pro')->setFloat('price', 4999.99)->setVectorFp32('vector', [0.1, 0.9, 0.1, 0.2]);
    
    $phone = new ZVecDoc('prod_003');
    $phone->setString('name', 'iPhone 15')->setFloat('price', 5499.99)->setVectorFp32('vector', [0.2, 0.1, 0.9, 0.1]);
    
    $headphones = new ZVecDoc('prod_004');
    $headphones->setString('name', 'AirPods Pro')->setFloat('price', 1299.99)->setVectorFp32('vector', [0.3, 0.2, 0.1, 0.9]);
    
    $collection->insert($tablet, $phone, $headphones);
    echo "[5] Added 3 documents (batch)\n";

    // Optimization - building index
    $collection->optimize();
    echo "[6] Index optimized\n";

    // Fetching documents
    $docs = $collection->fetch('prod_001', 'prod_002');
    echo "[7] Fetched documents:\n";
    foreach ($docs as $doc) {
        echo "    - {$doc->getString('name')}: {$doc->getFloat('price')} USD\n";
    }

    // Statistics
    echo "[8] Stats: " . $collection->stats() . "\n";

    $collection->destroy();
    echo "\n✓ Success!\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}
