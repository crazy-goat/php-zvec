--TEST--
Concurrency Options: test optimize, createIndex, addColumn, alterColumn with concurrency parameter
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/concurrency_test_' . uniqid();
try {
    // Create schema
    $schema = new ZVecSchema('test_collection');
    $schema->addInt64('id', false)
           ->addVectorFp32('embedding', 128);
    
    // Create collection
    $collection = ZVec::create($path, $schema);
    echo "Collection created\n";
    
    // Insert some data
    for ($i = 0; $i < 100; $i++) {
        $vector = [];
        for ($j = 0; $j < 128; $j++) {
            $vector[] = (float)rand() / (float)getrandmax();
        }
        $doc = new ZVecDoc("doc_$i");
        $doc->setInt64('id', $i)
            ->setVectorFp32('embedding', $vector);
        $collection->insert($doc);
    }
    echo "Inserted 100 documents\n";
    
    // Test optimize with concurrency
    $collection->optimize(concurrency: 2);
    echo "Optimize with concurrency=2 works\n";
    
    // Test createHnswIndex with concurrency
    $collection->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 100, concurrency: 2);
    echo "createHnswIndex with concurrency=2 works\n";
    
    // Test createFlatIndex with concurrency (on a new collection)
    $collection2Path = __DIR__ . '/../test_dbs/concurrency_flat_test_' . uniqid();
    $schema2 = new ZVecSchema('flat_test');
    $schema2->addInt64('id', false)
            ->addVectorFp32('flat_embedding', 64);
    $collection2 = ZVec::create($collection2Path, $schema2);
    
    // Insert data
    for ($i = 0; $i < 50; $i++) {
        $vector = [];
        for ($j = 0; $j < 64; $j++) {
            $vector[] = (float)rand() / (float)getrandmax();
        }
        $doc = new ZVecDoc("doc_$i");
        $doc->setInt64('id', $i)
            ->setVectorFp32('flat_embedding', $vector);
        $collection2->insert($doc);
    }
    
    $collection2->createFlatIndex('flat_embedding', metricType: ZVecSchema::METRIC_IP, concurrency: 2);
    echo "createFlatIndex with concurrency=2 works\n";
    
    // Cleanup collection2
    $collection2->close();
    exec("rm -rf " . escapeshellarg($collection2Path));
    
    // Test addColumn with concurrency
    $collection->addColumnInt64('new_field', true, '0', concurrency: 2);
    echo "addColumnInt64 with concurrency=2 works\n";
    
    $collection->addColumnFloat('float_field', true, '0.0', concurrency: 2);
    echo "addColumnFloat with concurrency=2 works\n";
    
    // Test alterColumn with concurrency (rename)
    $collection->alterColumn('new_field', newName: 'renamed_field', concurrency: 2);
    echo "alterColumn (rename) with concurrency=2 works\n";
    
    // Test renameColumn with concurrency
    $collection->renameColumn('renamed_field', 'final_name', concurrency: 2);
    echo "renameColumn with concurrency=2 works\n";
    
    // Test with default concurrency (0 = auto-detect) - using supported numeric type
    $collection->addColumnDouble('double_field', true, '0.0');
    echo "addColumnDouble with default concurrency works\n";
    
    echo "\nAll concurrency tests passed!\n";
    
} finally {
    if (isset($collection)) {
        $collection->close();
    }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Collection created
Inserted 100 documents
Optimize with concurrency=2 works
createHnswIndex with concurrency=2 works
createFlatIndex with concurrency=2 works
addColumnInt64 with concurrency=2 works
addColumnFloat with concurrency=2 works
alterColumn (rename) with concurrency=2 works
renameColumn with concurrency=2 works
addColumnDouble with default concurrency works

All concurrency tests passed!
