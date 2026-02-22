<?php

/**
 * Sparse Vector Example
 * 
 * Demonstrates how to use sparse vectors in zvec-php.
 * Sparse vectors are useful for keyword/semantic hybrid search where
 * only a few dimensions have non-zero values.
 * 
 * In zvec, sparse vectors are stored as pairs of (indices, values):
 * - indices: array of dimension positions (uint32)
 * - values: array of weights at those positions (float32)
 */

require_once __DIR__ . '/ZVec.php';

// Initialize with console logging
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_INFO);

$path = __DIR__ . '/../test_dbs/example_sparse_' . uniqid();

try {
    echo "=== Sparse Vector Example ===\n\n";

    // 1. Create schema with sparse vector field
    echo "1. Creating schema with sparse vector field...\n";
    $schema = new ZVecSchema('documents');
    $schema->addString('title', withInvertIndex: true)
           ->addString('content', withInvertIndex: true)
           ->addSparseVectorFp32('keywords', metricType: ZVecSchema::METRIC_IP);
    echo "   ✓ Schema created with sparse vector field 'keywords'\n\n";

    // 2. Create collection
    echo "2. Creating collection...\n";
    $coll = ZVec::create($path, schema: $schema);
    echo "   ✓ Collection created at: $path\n\n";

    // 3. Create documents with sparse vectors
    echo "3. Creating documents with sparse vectors...\n";
    
    // Sparse vectors represent keyword importance
    // Format: indices = [dimension_id], values = [weight]
    // In this example, dimension_id maps to keyword_id
    
    $doc1 = new ZVecDoc('doc1');
    $doc1->setString('title', 'PHP Vector Database Guide')
          ->setString('content', 'Learn how to use zvec-php for vector search')
          ->setSparseVectorFp32('keywords', 
              indices: [100, 200, 300],  // dimension IDs for: php, vector, database
              values:  [0.9, 0.8, 0.7]   // weights
          );
    echo "   doc1: PHP[0.9], Vector[0.8], Database[0.7]\n";

    $doc2 = new ZVecDoc('doc2');
    $doc2->setString('title', 'Machine Learning Basics')
          ->setString('content', 'Introduction to ML and neural networks')
          ->setSparseVectorFp32('keywords',
              indices: [200, 400, 500],  // vector, ml, neural
              values:  [0.6, 0.9, 0.8]
          );
    echo "   doc2: Vector[0.6], ML[0.9], Neural[0.8]\n";

    $doc3 = new ZVecDoc('doc3');
    $doc3->setString('title', 'PHP FFI Tutorial')
          ->setString('content', 'Using FFI to call C libraries from PHP')
          ->setSparseVectorFp32('keywords',
              indices: [100, 600],  // php, ffi
              values:  [0.95, 0.85]
          );
    echo "   doc3: PHP[0.95], FFI[0.85]\n";

    $doc4 = new ZVecDoc('doc4');
    $doc4->setString('title', 'Database Indexing Strategies')
          ->setString('content', 'How to optimize database queries with indexes')
          ->setSparseVectorFp32('keywords',
              indices: [300, 700],  // database, indexing
              values:  [0.9, 0.8]
          );
    echo "   doc4: Database[0.9], Indexing[0.8]\n\n";

    // 4. Insert documents
    echo "4. Inserting documents...\n";
    $coll->insert($doc1, $doc2, $doc3, $doc4);
    echo "   ✓ 4 documents inserted\n\n";

    // 5. Retrieve and display sparse vectors
    echo "5. Retrieving sparse vectors from stored documents...\n";
    $retrieved = $coll->fetch('doc1', 'doc2', 'doc3', 'doc4');
    
    // Create map by PK since fetch returns docs in arbitrary order
    $docMap = [];
    foreach ($retrieved as $doc) {
        $docMap[$doc->getPk()] = $doc;
    }
    
    foreach (['doc1', 'doc2', 'doc3', 'doc4'] as $pk) {
        $doc = $docMap[$pk];
        $sparse = $doc->getSparseVectorFp32('keywords');
        echo "   $pk: " . $doc->getString('title') . "\n";
        echo "        Indices: [" . implode(', ', $sparse['indices']) . "]\n";
        echo "        Values:  [" . implode(', ', array_map(fn($v) => round($v, 2), $sparse['values'])) . "]\n";
    }
    echo "\n";

    // 6. Demonstrate error handling
    echo "6. Error handling (mismatched array lengths)...\n";
    $badDoc = new ZVecDoc('bad');
    try {
        $badDoc->setSparseVectorFp32('keywords', [1, 2, 3], [0.5, 0.3]); // 3 indices, 2 values
        echo "   ✗ Should have thrown exception!\n";
    } catch (ZVecException $e) {
        echo "   ✓ Correctly caught: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 7. Demonstrate empty sparse vector
    echo "7. Empty sparse vector...\n";
    $emptyDoc = new ZVecDoc('empty');
    $emptyDoc->setString('title', 'Empty Document')
             ->setString('content', 'Document with empty sparse vector')
             ->setSparseVectorFp32('keywords', [], []);
    $coll->insert($emptyDoc);
    
    $retrievedEmpty = $coll->fetch('empty');
    $sparseEmpty = $retrievedEmpty[0]->getSparseVectorFp32('keywords');
    echo "   ✓ Empty doc has " . count($sparseEmpty['indices']) . " indices (count: 0)\n";
    echo "\n";

    // Close collection
    $coll->close();
    
    echo "=== Example Complete ===\n";
    echo "\nNotes:\n";
    echo "- Sparse vectors are stored as pairs of (indices, values)\n";
    echo "- Each index represents a dimension/keyword ID\n";
    echo "- Values represent importance/weight of that dimension\n";
    echo "- Useful for: keyword search, TF-IDF, BM25, hybrid semantic+keyword search\n";
    
} catch (Throwable $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} finally {
    // Cleanup
    if (isset($path)) {
        exec("rm -rf " . escapeshellarg($path));
    }
}
