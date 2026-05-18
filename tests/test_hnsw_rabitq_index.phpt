--TEST--
HNSW RaBitQ index: create, insert with dim >= 64, optimize, query
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_ERROR);

$path = __DIR__ . '/../test_dbs/hnsw_rabitq_' . uniqid();
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

    // Insert some docs with distinct vectors
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
    $results = $coll->query($query);

    echo "Results: " . count($results) . "\n";
    echo "Top pk: " . $results[0]->getPk() . "\n";

    // Query with legacy method
    $results2 = $coll->query('vec', $data[0], topk: 3, queryParamType: ZVec::QUERY_PARAM_HNSW_RABITQ, hnswEf: 100);
    echo "Legacy results: " . count($results2) . "\n";

    $coll->close();

    // Test via deprecated convenience method
    $coll2 = ZVec::create($path . '_2', $schema);
    $coll2->createHnswRabitqIndex('vec');
    $doc2 = new ZVecDoc('d1');
    $doc2->setInt64('id', 1);
    $doc2->setVectorFp32('vec', array_fill(0, 64, 0.1));
    $coll2->insert($doc2);
    $coll2->optimize();
    echo "Deprecated method works\n";
    $coll2->destroy();

    echo "OK\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
    exec("rm -rf " . escapeshellarg($path . '_2'));
}
?>
--EXPECTF--
%AResults: %d
Top pk: doc0
Legacy results: 3
%ADeprecated method works
OK
