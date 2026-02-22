<?php

declare(strict_types=1);

/**
 * Example 4: Schema and Index Management
 * 
 * Demonstrates:
 * - Adding new columns to existing collection
 * - Renaming columns
 * - Changing column data types
 * - Creating vector indexes (HNSW, Flat)
 * - Dropping indexes
 */

require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/example_04_' . uniqid();

try {
    echo "=== Example 4: Schema and Index Management ===\n\n";

    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

    // Initial schema
    $schema = new ZVecSchema('data');
    $schema
        ->addString('title', nullable: false)
        ->addInt64('year', nullable: false)
        ->addVectorFp32('vector', dimension: 3, metricType: ZVecSchema::METRIC_IP);

    $collection = ZVec::create($path, $schema);

    // Add test data
    $movies = [
        ['Inception', 2010, [0.9, 0.8, 0.7]],
        ['Matrix', 1999, [0.85, 0.75, 0.9]],
        ['Interstellar', 2014, [0.8, 0.9, 0.6]],
    ];

    foreach ($movies as $i => $m) {
        $doc = new ZVecDoc('movie_' . ($i + 1));
        $doc->setString('title', $m[0])
            ->setInt64('year', $m[1])
            ->setVectorFp32('vector', $m[2]);
        $collection->insert($doc);
    }
    echo "[1] Created collection with 3 movies\n";
    echo "    Initial schema:\n{$collection->schema()}\n\n";

    // 1. Adding a column
    echo "[2] Adding 'rating' column (FLOAT):\n";
    $collection->addColumnFloat('rating', nullable: true, defaultExpr: '0');
    
    // Check - fetch existing movie (will have default value)
    $doc = $collection->fetch('movie_1')[0];
    echo "    Movie '{$doc->getString('title')}' has default rating: {$doc->getFloat('rating')}\n\n";

    // 2. Renaming a column
    echo "[3] Rename 'year' -> 'release_year':\n";
    $collection->renameColumn('year', 'release_year');
    
    $doc = $collection->fetch('movie_1')[0];
    echo "    Inception release year: {$doc->getInt64('release_year')}\n\n";

    // 3. Changing column type (INT64 -> FLOAT)
    echo "[4] Add and change type of 'value' column:\n";
    $collection->addColumnInt64('value', nullable: true, defaultExpr: '100');
    $doc = $collection->fetch('movie_1')[0];
    echo "    Before change: {$doc->getInt64('value')}\n";
    
    $collection->alterColumn('value', newDataType: ZVec::TYPE_FLOAT, nullable: true);
    $doc = $collection->fetch('movie_1')[0];
    echo "    After change: {$doc->getFloat('value')}\n\n";

    // 4. Creating vector indexes
    echo "[5] Managing vector indexes:\n";
    
    // First optimize
    $collection->optimize();
    echo "    - Current index: default\n";
    
    // Create Flat index (accurate but slower)
    $collection->createFlatIndex('vector', metricType: ZVecSchema::METRIC_IP);
    $collection->optimize();
    echo "    - Created Flat index\n";
    
    // Test search
    $results = $collection->query('vector', [0.9, 0.8, 0.7], topk: 2);
    echo "    - Search with Flat: {$results[0]->getString('title')}\n";
    
    // Change to HNSW (faster, approximate)
    $collection->dropIndex('vector');
    $collection->createHnswIndex('vector', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 100);
    $collection->optimize();
    echo "    - Changed to HNSW index\n";
    
    $results = $collection->query('vector', [0.9, 0.8, 0.7], topk: 2);
    echo "    - Search with HNSW: {$results[0]->getString('title')}\n\n";

    // 5. Show final schema
    echo "[6] Final schema:\n{$collection->schema()}\n";

    $collection->destroy();
    echo "✓ Success!\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}
