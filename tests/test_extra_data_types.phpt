--TEST--
Extra data types: BOOL, INT32, UINT32, UINT64, VECTOR_INT8
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/extra_data_types_' . uniqid();
try {
    // Test schema with all new scalar types
    $schema = new ZVecSchema('extra_types_test');
    $schema->addInt64('id', nullable: false, withInvertIndex: true)
        ->addBool('active', nullable: false, withInvertIndex: true)
        ->addInt32('count', nullable: false, withInvertIndex: true)
        ->addUint32('u_count', nullable: true)
        ->addUint64('u_id', nullable: true)
        ->addVectorInt8('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    
    $collection = ZVec::create($path, $schema);
    
    // Test document with new types
    $doc = new ZVecDoc('doc_1');
    $doc->setInt64('id', 1)
        ->setBool('active', true)
        ->setInt32('count', 100)
        ->setUint32('u_count', 50)
        ->setUint64('u_id', 123456789)
        ->setVectorInt8('embedding', [1, 2, 3, 4]);
    
    $collection->insert($doc);
    $collection->optimize();
    
    // Fetch and verify
    $results = $collection->fetch('doc_1');
    assert(count($results) === 1, "Should fetch 1 doc");
    
    $fetched = $results[0];
    assert($fetched->getInt64('id') === 1, "INT64 mismatch");
    assert($fetched->getBool('active') === true, "BOOL mismatch");
    assert($fetched->getInt32('count') === 100, "INT32 mismatch");
    assert($fetched->getUint32('u_count') === 50, "UINT32 mismatch");
    assert($fetched->getUint64('u_id') === 123456789, "UINT64 mismatch");
    
    $vec = $fetched->getVectorInt8('embedding');
    assert($vec === [1, 2, 3, 4], "VECTOR_INT8 mismatch: " . json_encode($vec));
    
    // Test column DDL with new types (BOOL not supported for column DDL)
    $collection->addColumnInt32('temp_val', nullable: true, defaultExpr: '0');
    $collection->addColumnUint32('flags', nullable: true, defaultExpr: '0');
    $collection->addColumnUint64('big_id', nullable: true, defaultExpr: '0');
    
    // Insert new doc to test defaults
    $doc2 = new ZVecDoc('doc_2');
    $doc2->setInt64('id', 2)
        ->setBool('active', false)
        ->setInt32('count', 200)
        ->setVectorInt8('embedding', [5, 6, 7, 8]);
    
    $collection->insert($doc2);
    $collection->optimize();
    
    // Test alterColumn with new types
    $collection->alterColumn('temp_val', newDataType: ZVec::TYPE_FLOAT, nullable: true);
    
    // Verify schema changes
    $schemaStr = $collection->schema();
    assert(strpos($schemaStr, 'temp_val') !== false, "addColumnInt32 failed");
    assert(strpos($schemaStr, 'flags') !== false, "addColumnUint32 failed");
    assert(strpos($schemaStr, 'big_id') !== false, "addColumnUint64 failed");
    
    echo "All extra data types work correctly\n";
    
    $collection->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
All extra data types work correctly
