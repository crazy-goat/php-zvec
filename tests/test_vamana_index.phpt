--TEST--
Vamana index: create via unified IndexParams API, insert, optimize, query, query with ZVecVectorQuery
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
require_once __DIR__ . '/../src/ZVecRrfReRanker.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/vamana_' . uniqid();

try {
    $schema = new ZVecSchema('vamana_test');
    $schema->addInt64('id', nullable: false)
        ->addString('label', nullable: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Test 1: Create Vamana index via ZVecIndexParams::forVamana()
    $params = ZVecIndexParams::forVamana(
        metricType: ZVecSchema::METRIC_IP,
        maxDegree: 32,
        searchListSize: 50,
        alpha: 1.0,
        saturateGraph: false,
        quantizeType: ZVec::QUANTIZE_UNDEFINED
    );
    $c->createIndex('v', $params);
    echo "Vamana index created OK\n";

    // Insert docs and optimize
    $docs = [
        (new ZVecDoc('doc1'))->setInt64('id', 1)->setString('label', 'a')->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0]),
        (new ZVecDoc('doc2'))->setInt64('id', 2)->setString('label', 'b')->setVectorFp32('v', [0.0, 1.0, 0.0, 0.0]),
        (new ZVecDoc('doc3'))->setInt64('id', 3)->setString('label', 'c')->setVectorFp32('v', [0.0, 0.0, 1.0, 0.0]),
        (new ZVecDoc('doc4'))->setInt64('id', 4)->setString('label', 'd')->setVectorFp32('v', [0.0, 0.0, 0.0, 1.0]),
        (new ZVecDoc('doc5'))->setInt64('id', 5)->setString('label', 'e')->setVectorFp32('v', [0.9, 0.1, 0.0, 0.0]),
    ];
    $c->insert(...$docs);
    $c->optimize();
    echo "Inserted 5 docs and optimized OK\n";

    // Test 2: Query with default params
    $results = $c->query('v', [1.0, 0.1, 0.0, 0.0], topk: 3);
    assert(count($results) === 3, 'Expected 3 results');
    echo "Query (default) returned 3 results, top: " . $results[0]->getPk() . "\n";

    // Test 3: Query with ZVecVectorQuery and setVamanaParams
    $vq = new ZVecVectorQuery('v', [1.0, 0.1, 0.0, 0.0]);
    $vq->setVamanaParams(efSearch: 50);
    $results2 = $c->query($vq, topk: 3);
    assert(count($results2) === 3, 'Expected 3 results');
    echo "Query (Vamana efSearch=50) returned 3 results, top: " . $results2[0]->getPk() . "\n";

    // Test 4: Recreate index with different params
    $c->dropIndex('v');
    $params2 = ZVecIndexParams::forVamana(
        metricType: ZVecSchema::METRIC_IP,
        maxDegree: 16,
        searchListSize: 30,
        alpha: 1.2,
        saturateGraph: false
    );
    $c->createIndex('v', $params2);
    $c->optimize();
    echo "Vamana index recreated with custom params OK\n";

    $results3 = $c->query('v', [1.0, 0.1, 0.0, 0.0], topk: 3);
    assert(count($results3) === 3, 'Expected 3 results');
    echo "Query after recreate OK\n";

    // Test 5: queryByFilter
    $filterResults = $c->queryByFilter('id >= 3', topk: 10);
    assert(count($filterResults) > 0, 'Expected results from filter query');
    echo "queryByFilter returned " . count($filterResults) . " results OK\n";

    // Test 6: Error - wrong query param type vs actual index
    try {
        $vqBad = new ZVecVectorQuery('v', [1.0, 0.1, 0.0, 0.0]);
        $vqBad->setHnswParams(ef: 200);
        $c->query($vqBad, topk: 3);
        echo "UNEXPECTED: Should have thrown\n";
    } catch (ZVecException $e) {
        echo "Query param type mismatch correctly caught: " . $e->getErrorCodeString() . "\n";
    }

    $c->close();
    echo "PASS: All Vamana index scenarios work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Vamana index created OK
Inserted 5 docs and optimized OK
Query (default) returned 3 results, top: doc1
Query (Vamana efSearch=50) returned 3 results, top: doc1
Vamana index recreated with custom params OK
Query after recreate OK
queryByFilter returned 3 results OK
Query param type mismatch correctly caught: INVALID_ARGUMENT
PASS: All Vamana index scenarios work
