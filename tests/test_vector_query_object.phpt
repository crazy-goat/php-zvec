--TEST--
VectorQuery object: queryVector(), groupByVectorQuery(), backward compat
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/vector_query_' . uniqid();
try {
    $schema = new ZVecSchema('test_collection');
    $schema->addVectorFp32('embedding', 4, ZVecSchema::METRIC_IP);
    $schema->addString('title', nullable: false, withInvertIndex: true);

    $coll = ZVec::create($path, $schema);

    $docs = [
        (new ZVecDoc('doc1'))->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0])->setString('title', 'First doc'),
        (new ZVecDoc('doc2'))->setVectorFp32('embedding', [0.0, 1.0, 0.0, 0.0])->setString('title', 'Second doc'),
        (new ZVecDoc('doc3'))->setVectorFp32('embedding', [0.0, 0.0, 1.0, 0.0])->setString('title', 'Third doc'),
    ];
    $coll->insert(...$docs);
    $coll->optimize();

    // Test 1: Old API backward compat
    $results = $coll->query('embedding', [1.0, 0.0, 0.0, 0.0], 3);
    echo "Old API: Found " . count($results) . " results, first pk: " . $results[0]->getPk() . "\n";

    // Test 2: queryVector() with ZVecVectorQuery
    $vq = new ZVecVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0]);
    $vq->setTopk(3);
    $results = $coll->queryVector($vq);
    echo "queryVector: Found " . count($results) . " results, first pk: " . $results[0]->getPk() . "\n";

    // Test 3: ZVecVectorQuery via old query() method
    $vq2 = new ZVecVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0]);
    $results = $coll->query($vq2, [], 3);
    echo "ZVecVectorQuery->query(): Found " . count($results) . " results, first pk: " . $results[0]->getPk() . "\n";

    // Test 4: queryVector with HNSW params
    $coll->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 100);
    $coll->optimize();

    $vq3 = (new ZVecVectorQuery('embedding', [0.0, 1.0, 0.0, 0.0]))
        ->setTopk(3)
        ->setHnswParams(ef: 200);
    $results = $coll->queryVector($vq3);
    echo "queryVector HNSW: Found " . count($results) . " results, first pk: " . $results[0]->getPk() . "\n";

    // Test 5: queryVector with radius
    $vq4 = (new ZVecVectorQuery('embedding', [1.0, 1.0, 0.0, 0.0]))
        ->setTopk(10)
        ->setRadius(1.5);
    $results = $coll->queryVector($vq4);
    echo "queryVector radius: Found " . count($results) . " results\n";

    // Test 6: queryVector with filter
    $vq5 = (new ZVecVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0]))
        ->setTopk(3)
        ->setFilter("title = 'First doc'");
    $results = $coll->queryVector($vq5);
    echo "queryVector filter: Found " . count($results) . " results, pk: " . $results[0]->getPk() . "\n";

    // Test 7: queryVector with output fields
    $vq6 = (new ZVecVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0]))
        ->setTopk(3)
        ->setOutputFields(['title']);
    $results = $coll->queryVector($vq6);
    echo "queryVector outputFields: Found " . count($results) . " results, title: " . $results[0]->getString('title') . "\n";

    // Test 8: include vector
    $vq7 = (new ZVecVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0]))
        ->setTopk(3)
        ->setIncludeVector(true);
    $results = $coll->queryVector($vq7);
    $vec = $results[0]->getVectorFp32('embedding');
    echo "queryVector includeVector: " . ($vec !== null ? 'vector present' : 'no vector') . "\n";

    // Test 9: ZVecGroupByVectorQuery via groupByVectorQuery()
    $coll->insert(
        (new ZVecDoc('doc4'))->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0])->setString('title', 'GroupA'),
        (new ZVecDoc('doc5'))->setVectorFp32('embedding', [1.0, 0.0, 0.0, 0.0])->setString('title', 'GroupA'),
        (new ZVecDoc('doc6'))->setVectorFp32('embedding', [0.0, 1.0, 0.0, 0.0])->setString('title', 'GroupB'),
    );
    $coll->optimize();

    $gvq = new ZVecGroupByVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0], 'title', 2, 2);
    $groups = $coll->groupByVectorQuery($gvq);
    echo "groupByVectorQuery: Found " . count($groups) . " groups\n";

    // Test 10: Legacy groupByQuery with VectorQuery still works
    $groups2 = $coll->groupByQuery(
        new ZVecVectorQuery('embedding', [1.0, 0.0, 0.0, 0.0]),
        [],
        'title',
        2,
        2
    );
    echo "Legacy groupByQuery: Found " . count($groups2) . " groups\n";

    // Test 11: queryVector with setLinear and setFlatParams
    $coll->createFlatIndex('embedding', metricType: ZVecSchema::METRIC_IP);
    $coll->optimize();

    $vq8 = (new ZVecVectorQuery('embedding', [0.5, 0.5, 0.0, 0.0]))
        ->setTopk(3)
        ->setFlatParams()
        ->setLinear(true);
    $results = $coll->queryVector($vq8);
    echo "queryVector Flat+Linear: Found " . count($results) . " results\n";

    echo "\nAll tests passed!\n";

} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Old API: Found 3 results, first pk: doc1
queryVector: Found 3 results, first pk: doc1
ZVecVectorQuery->query(): Found 3 results, first pk: doc1
queryVector HNSW: Found 3 results, first pk: doc2
queryVector radius: Found %d results
queryVector filter: Found 1 results, pk: doc1
queryVector outputFields: Found 3 results, title: First doc
queryVector includeVector: vector present
groupByVectorQuery: Found %d groups
Legacy groupByQuery: Found %d groups
queryVector Flat+Linear: Found 3 results

All tests passed!
