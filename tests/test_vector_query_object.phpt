--TEST--
VectorQuery object: basic functionality and backward compatibility
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/vector_query_' . uniqid();
try {
    // Create schema with vector field
    $schema = new ZVecSchema('test_collection');
    $schema->addVectorFp32('embedding', 4, ZVecSchema::METRIC_IP);
    $schema->addString('title', nullable: false, withInvertIndex: true);

    // Create collection
    $coll = ZVec::create($path, $schema);

    // Insert test documents
    $docs = [
        (new ZVecDoc('doc1'))->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0])->setString('title', 'First doc'),
        (new ZVecDoc('doc2'))->setVectorFp32('embedding', [0.0, 1.0, 0.0, 0.0])->setString('title', 'Second doc'),
        (new ZVecDoc('doc3'))->setVectorFp32('embedding', [0.0, 0.0, 1.0, 0.0])->setString('title', 'Third doc'),
    ];
    $coll->insert(...$docs);
    $coll->optimize();

    // Test 1: Old API (backward compatibility) - using positional args with QUERY_PARAM_NONE
    $results = $coll->query(
        'embedding',
        [1.0, 0.0, 0.0, 0.0],
        3,
        false,
        null,
        null,
        ZVec::QUERY_PARAM_NONE
    );
    echo "Old API: Found " . count($results) . " results, first pk: " . $results[0]->getPk() . "\n";

    // Test 2: New API with VectorQuery object
    $vq = new ZVecVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0]);
    $results = $coll->query($vq, [], 3, false, null, null, ZVec::QUERY_PARAM_NONE);
    echo "New API (flat): Found " . count($results) . " results, first pk: " . $results[0]->getPk() . "\n";

    // Test 3: VectorQuery with HNSW params
    $coll->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 100);
    $coll->optimize();

    $vq2 = (new ZVecVectorQuery('embedding', [0.0, 1.0, 0.0, 0.0]))->setHnswParams(ef: 200);
    $results = $coll->query($vq2, [], 3);
    echo "New API (HNSW): Found " . count($results) . " results, first pk: " . $results[0]->getPk() . "\n";

    // Test 4: VectorQuery with radius
    $vq3 = (new ZVecVectorQuery('embedding', [1.0, 1.0, 0.0, 0.0]))->setRadius(1.5);
    $results = $coll->query($vq3, [], 10);
    echo "New API (radius): Found " . count($results) . " results\n";

    // Test 5: VectorQuery fromId (should throw - not implemented yet)
    $vq4 = ZVecVectorQuery::fromId('embedding', 'doc1');
    try {
        $coll->query($vq4, [], 3);
        echo "ERROR: Should have thrown exception for docId query\n";
    } catch (ZVecException $e) {
        echo "Expected exception: " . $e->getMessage() . "\n";
    }

    // Test 6: groupByQuery with VectorQuery
    $coll->insert(
        (new ZVecDoc('doc4'))->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0])->setString('title', 'GroupA'),
        (new ZVecDoc('doc5'))->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0])->setString('title', 'GroupA'),
        (new ZVecDoc('doc6'))->setVectorFp32('embedding', [0.0, 1.0, 0.0, 0.0])->setString('title', 'GroupB'),
    );
    $coll->optimize();

    $groups = $coll->groupByQuery(
        new ZVecVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0]),
        [], // vector will be overridden by VectorQuery
        'title',
        2,
        2
    );
    echo "GroupByQuery with VectorQuery: Found " . count($groups) . " groups\n";

    // Test 7: Fluent interface (using setFlatParams but still needs a flat index)
    // First create a flat index
    $coll->createFlatIndex('embedding', metricType: ZVecSchema::METRIC_IP);
    $coll->optimize();
    
    $vq5 = (new ZVecVectorQuery('embedding', [0.5, 0.5, 0.0, 0.0]))
        ->setFlatParams()
        ->setRadius(0.5)
        ->setLinear(true);
    $results = $coll->query($vq5, [], 3);
    echo "Fluent API: Found " . count($results) . " results\n";

    echo "\nAll tests passed!\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Old API: Found 3 results, first pk: doc1
New API (flat): Found 3 results, first pk: doc1
New API (HNSW): Found 3 results, first pk: doc2
New API (radius): Found 3 results
Expected exception: query() with docId not yet implemented. Use queryById() or fetch the vector first.
GroupByQuery with VectorQuery: Found 1 groups
Fluent API: Found 3 results

All tests passed!
