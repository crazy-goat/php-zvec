<?php

/**
 * Example: VectorQuery Object
 * 
 * Demonstrates the new ZVecVectorQuery class for structured vector queries.
 * This provides a cleaner API compared to passing multiple separate parameters.
 */

require_once __DIR__ . '/ZVec.php';

// Initialize
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../demo_vector_query';
if (is_dir($path)) {
    exec("rm -rf " . escapeshellarg($path));
}

// Create collection with vector field
$schema = new ZVecSchema('vector_query_demo');
$schema->addVectorFp32('embedding', dimension: 128, metricType: ZVecSchema::METRIC_IP)
    ->addString('title', nullable: false, withInvertIndex: true)
    ->addString('category', nullable: false, withInvertIndex: true);

$collection = ZVec::create($path, $schema);
$collection->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 100);
$collection->optimize();

echo "=== VectorQuery Object Demo ===\n\n";

// ==========================================
// Insert sample documents
// ==========================================
echo "Inserting sample documents...\n";
$docs = [
    (new ZVecDoc('doc1'))
        ->setString('title', 'Introduction to Machine Learning')
        ->setString('category', 'tech')
        ->setVectorFp32('embedding', array_merge([1.0, 0.9, 0.8], array_fill(3, 125, 0.1))),
    (new ZVecDoc('doc2'))
        ->setString('title', 'Deep Learning Fundamentals')
        ->setString('category', 'tech')
        ->setVectorFp32('embedding', array_merge([0.9, 1.0, 0.85], array_fill(3, 125, 0.15))),
    (new ZVecDoc('doc3'))
        ->setString('title', 'PHP Best Practices')
        ->setString('category', 'programming')
        ->setVectorFp32('embedding', array_merge([0.2, 0.3, 0.4], array_fill(3, 125, 0.05))),
    (new ZVecDoc('doc4'))
        ->setString('title', 'Vector Databases Overview')
        ->setString('category', 'tech')
        ->setVectorFp32('embedding', array_merge([0.95, 0.88, 0.92], array_fill(3, 125, 0.12))),
];
$collection->insert(...$docs);
$collection->optimize();
echo "Inserted " . count($docs) . " documents\n\n";

// ==========================================
// 1. Basic VectorQuery usage
// ==========================================
echo "1. Basic VectorQuery usage:\n";

// Create a query vector similar to tech documents
$queryVector = array_merge([0.9, 0.9, 0.9], array_fill(3, 125, 0.1));

// Method A: Old API (still works for backward compatibility)
$results = $collection->query(
    'embedding',
    $queryVector,
    3,  // topk
    false,  // includeVector
    null,  // filter
    ['title', 'category'],  // outputFields
    ZVec::QUERY_PARAM_HNSW,  // queryParamType
    200  // hnswEf
);
echo "   Old API (positional): " . count($results) . " results\n";

// Method B: New API with VectorQuery object
$vq = new ZVecVectorQuery('embedding', $queryVector);
$vq->setHnswParams(ef: 200);

$results = $collection->query($vq, [], 3, false, null, ['title', 'category']);
echo "   New API (VectorQuery): " . count($results) . " results\n";

foreach ($results as $doc) {
    echo "     - {$doc->getString('title')} [{$doc->getString('category')}]\n";
}
echo "\n";

// ==========================================
// 2. Fluent interface
// ==========================================
echo "2. Fluent interface (method chaining):\n";

$results = $collection->query(
    (new ZVecVectorQuery('embedding', $queryVector))
        ->setHnswParams(ef: 300)
        ->setRadius(0.5)
        ->setLinear(false),
    [],
    5,
    false,
    null,
    ['title']
);
echo "   Query with chained setters: " . count($results) . " results\n";
foreach ($results as $doc) {
    echo "     - {$doc->getString('title')}\n";
}
echo "\n";

// ==========================================
// 3. Different index types
// ==========================================
echo "3. Query parameters for different index types:\n";

// Create a flat index for comparison
$collection->dropIndex('embedding');
$collection->createFlatIndex('embedding', metricType: ZVecSchema::METRIC_IP);
$collection->optimize();

// Flat index query
$flatQuery = (new ZVecVectorQuery('embedding', $queryVector))->setFlatParams();
$results = $collection->query($flatQuery, [], 3);
echo "   Flat index query: " . count($results) . " results\n";

// Switch back to HNSW for better performance
$collection->dropIndex('embedding');
$collection->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP);
$collection->optimize();

// HNSW query with custom ef
$hnswQuery = (new ZVecVectorQuery('embedding', $queryVector))->setHnswParams(ef: 100);
$results = $collection->query($hnswQuery, [], 3);
echo "   HNSW query (ef=100): " . count($results) . " results\n\n";

// ==========================================
// 4. Filtered queries with VectorQuery
// ==========================================
echo "4. Filtered queries using VectorQuery:\n";

$filteredQuery = (new ZVecVectorQuery('embedding', $queryVector))
    ->setHnswParams(ef: 200);

// Query with filter - only tech category
$results = $collection->query($filteredQuery, [], 10, false, "category = 'tech'", ['title', 'category']);
echo "   Filtered by category='tech': " . count($results) . " results\n";
foreach ($results as $doc) {
    echo "     - {$doc->getString('title')} [{$doc->getString('category')}]\n";
}
echo "\n";

// ==========================================
// 5. Query with output field selection
// ==========================================
echo "5. Selective field retrieval:\n";

$selectiveQuery = new ZVecVectorQuery('embedding', $queryVector);
$results = $collection->query($selectiveQuery, [], 2, false, null, ['title']);  // Only title, no category

foreach ($results as $doc) {
    $title = $doc->getString('title');
    $category = $doc->getString('category');
    echo "     - Title: {$title}\n";
    echo "       Category: " . ($category ?? '(not fetched)') . "\n";
}
echo "\n";

// ==========================================
// 6. Preparing for multi-vector (future)
// ==========================================
echo "6. Preparing for multi-vector queries:\n";
echo "   VectorQuery objects can be stored in arrays:\n";

// This will be useful for multi-vector queries (task 05)
$queries = [
    new ZVecVectorQuery('embedding', array_merge([1.0, 0.9, 0.8], array_fill(3, 125, 0.1))),
    new ZVecVectorQuery('embedding', array_merge([0.2, 0.3, 0.4], array_fill(3, 125, 0.05))),
];

echo "   Created " . count($queries) . " VectorQuery objects\n";
echo "   (Multi-vector search coming in future update)\n\n";

// ==========================================
// Cleanup
// ==========================================
$collection->close();
$collection = ZVec::open($path);
$collection->destroy();

echo "Done! Collection destroyed.\n";
echo "\nKey takeaways:\n";
echo "- ZVecVectorQuery provides cleaner API than positional arguments\n";
echo "- Fluent interface allows method chaining for better readability\n";
echo "- Backward compatible: old API still works\n";
echo "- Supports all query parameters: HNSW, IVF, Flat, radius, etc.\n";
echo "- Query parameters are encapsulated in the object\n";
