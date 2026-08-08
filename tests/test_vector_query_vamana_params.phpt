--TEST--
Vector query params: queryVector() with setVamanaParams() on a Vamana index (issue #193)
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip This test uses ZVecIndexParams which only works via FFI'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/vamana_query_params_' . uniqid();
try {
    $schema = new ZVecSchema('vamana_test');
    $schema->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    $c->createIndex('v', ZVecIndexParams::forVamana(
        metricType: ZVecSchema::METRIC_IP,
        maxDegree: 32,
        searchListSize: 50,
        alpha: 1.0,
        saturateGraph: false,
    ));

    $c->insert(
        (new ZVecDoc('doc1'))->setInt64('id', 1)->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0]),
        (new ZVecDoc('doc2'))->setInt64('id', 2)->setVectorFp32('v', [0.0, 1.0, 0.0, 0.0]),
        (new ZVecDoc('doc3'))->setInt64('id', 3)->setVectorFp32('v', [0.0, 0.0, 1.0, 0.0]),
    );
    $c->optimize();

    $vq = new ZVecVectorQuery('v', [1.0, 0.1, 0.0, 0.0]);
    $vq->setVamanaParams(efSearch: 50);
    $results = $c->queryVector($vq);
    assert(count($results) === 3, 'Expected 3 results');
    echo "queryVector with Vamana params returned " . count($results) . " results, top: " . $results[0]->getPk() . "\n";

    try {
        $bad = new ZVecVectorQuery('v', [1.0, 0.1, 0.0, 0.0]);
        $bad->setHnswParams(ef: 200);
        $c->queryVector($bad);
        echo "UNEXPECTED: HNSW params on Vamana index should have thrown\n";
    } catch (ZVecException $e) {
        echo "HNSW params on Vamana index correctly rejected: " . $e->getErrorCodeString() . "\n";
    }

    $c->close();
    echo "PASS: queryVector with setVamanaParams works\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
queryVector with Vamana params returned 3 results, top: doc1
HNSW params on Vamana index correctly rejected: INVALID_ARGUMENT
PASS: queryVector with setVamanaParams works
