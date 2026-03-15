--TEST--
Per-document status on batch operations: insertBatch, upsertBatch, updateBatch
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/batch_ops_' . uniqid();
try {
    // Create collection
    $schema = new ZVecSchema('test_collection');
    $schema->addVectorFp32('embedding', dimension: 3);
    $collection = ZVec::create($path, $schema);
    
    // Create HNSW index for vector queries
    $collection->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP);
    $collection->optimize();
    
    // Test insertBatch with all new documents
    $docs = [
        (new ZVecDoc('doc1'))->setVectorFp32('embedding', [1.0, 0.0, 0.0]),
        (new ZVecDoc('doc2'))->setVectorFp32('embedding', [0.0, 1.0, 0.0]),
        (new ZVecDoc('doc3'))->setVectorFp32('embedding', [0.0, 0.0, 1.0]),
    ];
    $results = $collection->insertBatch(...$docs);
    
    echo "Insert batch results:\n";
    foreach ($results as $r) {
        echo "  {$r['pk']}: " . ($r['ok'] ? 'OK' : 'FAILED') . ($r['error'] ? " - {$r['error']}" : '') . "\n";
    }
    
    // Test insertBatch with duplicate (should fail for doc1)
    $docs2 = [
        (new ZVecDoc('doc1'))->setVectorFp32('embedding', [1.0, 1.0, 0.0]),
        (new ZVecDoc('doc4'))->setVectorFp32('embedding', [0.5, 0.5, 0.5]),
    ];
    $results2 = $collection->insertBatch(...$docs2);
    
    echo "\nInsert batch with duplicate:\n";
    foreach ($results2 as $r) {
        echo "  {$r['pk']}: " . ($r['ok'] ? 'OK' : 'FAILED') . ($r['error'] ? " - error" : '') . "\n";
    }
    
    // Test upsertBatch (should succeed for all, including existing)
    $docs3 = [
        (new ZVecDoc('doc1'))->setVectorFp32('embedding', [2.0, 0.0, 0.0]),
        (new ZVecDoc('doc5'))->setVectorFp32('embedding', [0.3, 0.3, 0.3]),
    ];
    $results3 = $collection->upsertBatch(...$docs3);
    
    echo "\nUpsert batch results:\n";
    foreach ($results3 as $r) {
        echo "  {$r['pk']}: " . ($r['ok'] ? 'OK' : 'FAILED') . "\n";
    }
    
    // Test updateBatch (should succeed for existing, fail for non-existing)
    $docs4 = [
        (new ZVecDoc('doc1'))->setVectorFp32('embedding', [3.0, 0.0, 0.0]),
        (new ZVecDoc('doc_nonexistent'))->setVectorFp32('embedding', [0.1, 0.1, 0.1]),
    ];
    $results4 = $collection->updateBatch(...$docs4);
    
    echo "\nUpdate batch results:\n";
    foreach ($results4 as $r) {
        echo "  {$r['pk']}: " . ($r['ok'] ? 'OK' : 'FAILED') . ($r['error'] ? " - error" : '') . "\n";
    }
    
    // Close collection before cleanup to avoid RocksDB errors
    $collection->close();
    
    echo "\nAll tests passed!\n";
    
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Insert batch results:
  doc1: OK
  doc2: OK
  doc3: OK

Insert batch with duplicate:
  doc1: FAILED - error
  doc4: OK

Upsert batch results:
  doc1: OK
  doc5: OK

Update batch results:
  doc1: OK
  doc_nonexistent: FAILED - error

All tests passed!
