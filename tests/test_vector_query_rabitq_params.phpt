--TEST--
Vector query params: queryVector() with setHnswRabitqParams() on an HNSW RaBitQ index (issue #193)
--SKIPIF--
<?php
if (!extension_loaded('ffi')) die('skip FFI extension not available');
if (PHP_OS_FAMILY !== 'Linux' || php_uname('m') !== 'x86_64') die('skip RaBitQ supported on Linux x86_64 only');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/rabitq_query_params_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addInt64('id');
    $schema->addVectorFp32('vec', dimension: 64, metricType: ZVecSchema::METRIC_IP);
    $coll = ZVec::create($path, $schema);

    $coll->createIndex('vec', ZVecIndexParams::forHnswRabitq(
        metricType: ZVecSchema::METRIC_IP,
        m: 50,
        efConstruction: 500,
    ));

    $data = [];
    for ($i = 0; $i < 10; $i++) {
        $vec = [];
        for ($j = 0; $j < 64; $j++) {
            $vec[] = $j === $i ? 1.0 : 0.0;
        }
        $data[] = $vec;
        $doc = new ZVecDoc('doc' . $i);
        $doc->setInt64('id', $i);
        $doc->setVectorFp32('vec', $vec);
        $coll->insert($doc);
    }
    $coll->optimize();

    $query = new ZVecVectorQuery('vec', $data[0]);
    $query->setHnswRabitqParams(ef: 100);
    $results = $coll->queryVector($query);
    assert(count($results) >= 1, 'Expected at least 1 result');
    echo "queryVector with HNSW RaBitQ params returned " . count($results) . " results, top: " . $results[0]->getPk() . "\n";

    try {
        $bad = new ZVecVectorQuery('vec', $data[0]);
        $bad->setHnswParams(ef: 200);
        $coll->queryVector($bad);
        echo "UNEXPECTED: HNSW params on RaBitQ index should have thrown\n";
    } catch (ZVecException $e) {
        echo "HNSW params on RaBitQ index correctly rejected: " . $e->getErrorCodeString() . "\n";
    }

    $coll->close();
    echo "PASS: queryVector with setHnswRabitqParams works\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
%AqueryVector with HNSW RaBitQ params returned %d results, top: doc0
%AHNSW params on RaBitQ index correctly rejected: INVALID_ARGUMENT
%APASS: queryVector with setHnswRabitqParams works
%A
