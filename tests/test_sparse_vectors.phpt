--TEST--
Sparse vector data operations: set, get, and query
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/sparse_vector_' . uniqid();
try {
    // Create schema with sparse vector field
    $schema = new ZVecSchema('test_sparse');
    $schema->addSparseVectorFp32('embedding', metricType: ZVecSchema::METRIC_IP);
    
    $coll = ZVec::create($path, schema: $schema);
    
    // Insert documents with sparse vectors
    $doc1 = new ZVecDoc('doc1');
    $doc1->setSparseVectorFp32('embedding', [1, 5, 10], [0.5, 0.3, 0.8]);
    
    $doc2 = new ZVecDoc('doc2');
    $doc2->setSparseVectorFp32('embedding', [2, 5, 15, 20], [0.7, 0.4, 0.6, 0.2]);
    
    $doc3 = new ZVecDoc('doc3');
    $doc3->setSparseVectorFp32('embedding', [1, 10], [0.9, 0.1]);
    
    $coll->insert($doc1, $doc2, $doc3);
    
    // Retrieve and verify sparse vectors - fetch returns docs in arbitrary order
    // so we need to match by PK
    $retrieved = $coll->fetch('doc1', 'doc2', 'doc3');
    
    // Create a map of PK -> doc for easier verification
    $docMap = [];
    foreach ($retrieved as $doc) {
        $docMap[$doc->getPk()] = $doc;
    }
    
    // Check doc1
    assert(isset($docMap['doc1']), "doc1 should be in results");
    $sparse1 = $docMap['doc1']->getSparseVectorFp32('embedding');
    assert($sparse1 !== null, "doc1 should have sparse vector");
    assert(count($sparse1['indices']) === 3, "doc1 should have 3 indices, got " . count($sparse1['indices']));
    assert($sparse1['indices'][0] === 1, "First index should be 1");
    assert(abs($sparse1['values'][2] - 0.8) < 0.001, "Third value should be 0.8, got " . $sparse1['values'][2]);
    
    // Check doc2
    assert(isset($docMap['doc2']), "doc2 should be in results");
    $sparse2 = $docMap['doc2']->getSparseVectorFp32('embedding');
    assert($sparse2 !== null, "doc2 should have sparse vector");
    assert(count($sparse2['indices']) === 4, "doc2 should have 4 indices, got " . count($sparse2['indices']));
    
    // Check doc3
    assert(isset($docMap['doc3']), "doc3 should be in results");
    $sparse3 = $docMap['doc3']->getSparseVectorFp32('embedding');
    assert($sparse3 !== null, "doc3 should have sparse vector");
    assert(count($sparse3['indices']) === 2, "doc3 should have 2 indices, got " . count($sparse3['indices']));
    
    // Test error: mismatched array lengths
    $doc5 = new ZVecDoc('doc5');
    try {
        $doc5->setSparseVectorFp32('embedding', [1, 2, 3], [0.5, 0.3]);
        echo "FAIL: Should have thrown exception for mismatched lengths\n";
    } catch (ZVecException $e) {
        echo "Correctly caught mismatched lengths error\n";
    }
    
    echo "All sparse vector tests passed\n";
    
    // Close collection before cleanup to avoid RocksDB errors
    $coll->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Correctly caught mismatched lengths error
All sparse vector tests passed
