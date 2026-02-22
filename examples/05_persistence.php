<?php

declare(strict_types=1);

/**
 * Example 5: Opening, Closing and Persistence
 * 
 * Demonstrates:
 * - Closing collection (close)
 * - Reopening collection (open)
 * - Opening in read-only mode
 * - Flush data to disk
 * - Destroying collection (destroy)
 */

require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/example_05_' . uniqid();

try {
    echo "=== Example 5: Opening, Closing and Persistence ===\n\n";

    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

    // Phase 1: Create and save data
    echo "[1] Creating collection and saving data:\n";
    
    $schema = new ZVecSchema('persistent_data');
    $schema->addString('name', nullable: false)
           ->addVectorFp32('vec', dimension: 2, metricType: ZVecSchema::METRIC_IP);
    
    $collection = ZVec::create($path, $schema);
    
    $doc1 = new ZVecDoc('id_1');
    $doc1->setString('name', 'First')->setVectorFp32('vec', [1.0, 0.0]);
    
    $doc2 = new ZVecDoc('id_2');
    $doc2->setString('name', 'Second')->setVectorFp32('vec', [0.0, 1.0]);
    
    $collection->insert($doc1, $doc2);
    $collection->optimize();
    $collection->flush(); // Force write to disk
    
    echo "    Created and saved 2 documents\n";
    echo "    Path: {$collection->path()}\n\n";

    // Phase 2: Close
    echo "[2] Closing collection:\n";
    $collection->close();
    echo "    Collection closed\n\n";

    // Phase 3: Reopen
    echo "[3] Reopening collection:\n";
    $collection = ZVec::open($path);
    
    $options = $collection->options();
    echo "    Opened (read_only: " . ($options['read_only'] ? 'yes' : 'no') . ")\n";
    
    $docs = $collection->fetch('id_1', 'id_2');
    echo "    Data preserved:\n";
    foreach ($docs as $doc) {
        echo "    - {$doc->getPk()}: {$doc->getString('name')}\n";
    }
    $collection->close();
    echo "\n";

    // Phase 4: Open in read-only mode
    echo "[4] Opening in read-only mode:\n";
    $collection = ZVec::open($path, readOnly: true);
    
    $options = $collection->options();
    echo "    Mode: " . ($options['read_only'] ? 'read-only' : 'read-write') . "\n";
    
    // Read works
    $docs = $collection->fetch('id_1');
    echo "    Read: {$docs[0]->getString('name')}\n";
    
    // Write would fail in read-only mode
    try {
        $doc3 = new ZVecDoc('id_3');
        $doc3->setString('name', 'Third')->setVectorFp32('vec', [0.5, 0.5]);
        $collection->insert($doc3);
        echo "    ERROR: Write in read-only should fail!\n";
    } catch (ZVecException $e) {
        echo "    OK: Write in read-only blocked\n";
    }
    
    $collection->close();
    echo "\n";

    // Phase 5: Destroy collection
    echo "[5] Destroying collection:\n";
    echo "    Directory exists before destroy: " . (is_dir($path) ? 'yes' : 'no') . "\n";
    
    $collection = ZVec::open($path);
    $collection->destroy();
    
    echo "    Directory exists after destroy: " . (is_dir($path) ? 'yes' : 'no') . "\n";
    echo "    (Note: after destroy(), the collection object is invalid,\n";
    echo "     cannot be reused)\n";

    echo "\n✓ Success!\n";

} finally {
    if (is_dir($path)) {
        exec("rm -rf " . escapeshellarg($path));
    }
}
