--TEST--
Bug #29: Fix segfault when using QUERY_PARAM_FLAT on HNSW index
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/bug29_' . uniqid();
try {
    // Create schema with vector field
    $schema = new ZVecSchema('test_collection');
    $schema->addVectorFp32('embedding', 128, ZVecSchema::METRIC_IP);
    
    // Create collection and add HNSW index
    $collection = ZVec::create($path, $schema);
    $collection->createHnswIndex('embedding', ZVecSchema::METRIC_IP, 16, 200);
    
    // Insert test document
    $doc = new ZVecDoc('doc1');
    $doc->setVectorFp32('embedding', array_fill(0, 128, 0.1));
    $collection->insert($doc);
    $collection->optimize();
    
    // Try to query with QUERY_PARAM_FLAT on HNSW index
    // This should throw ZVecException, not segfault
    $exceptionThrown = false;
    try {
        $results = $collection->query(
            fieldName: 'embedding',
            queryVector: array_fill(0, 128, 0.1),
            topk: 10,
            queryParamType: ZVec::QUERY_PARAM_FLAT  // Invalid - index is HNSW
        );
    } catch (ZVecException $e) {
        $exceptionThrown = true;
        echo "Exception caught: " . $e->getMessage() . "\n";
    }
    
    if (!$exceptionThrown) {
        echo "FAIL: No exception thrown for mismatched query_param_type\n";
        exit(1);
    }
    
    // Verify correct query with QUERY_PARAM_HNSW works
    $results = $collection->query(
        fieldName: 'embedding',
        queryVector: array_fill(0, 128, 0.1),
        topk: 10,
        queryParamType: ZVec::QUERY_PARAM_HNSW
    );
    echo "Valid query works: " . count($results) . " results\n";
    
    // Test groupByQuery as well
    $exceptionThrown = false;
    try {
        $results = $collection->groupByQuery(
            fieldName: 'embedding',
            queryVector: array_fill(0, 128, 0.1),
            groupByField: 'embedding',
            groupCount: 2,
            groupTopk: 3,
            queryParamType: ZVec::QUERY_PARAM_FLAT  // Invalid - index is HNSW
        );
    } catch (ZVecException $e) {
        $exceptionThrown = true;
        echo "GroupBy exception caught: " . $e->getMessage() . "\n";
    }
    
    if (!$exceptionThrown) {
        echo "FAIL: No exception thrown for groupByQuery with mismatched query_param_type\n";
        exit(1);
    }
    
    echo "PASS: All validations work correctly\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Exception caught: Query parameter type mismatch for field 'embedding': index type does not match query_param_type
Valid query works: 1 results
GroupBy exception caught: Query parameter type mismatch for field 'embedding': index type does not match query_param_type
PASS: All validations work correctly
