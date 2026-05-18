--TEST--
use_contiguous_memory: HNSW index with contiguous memory allocation
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/contigmem_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->addVectorFp32('vec', 4, ZVecSchema::METRIC_COSINE);
    $coll = ZVec::create($path, $schema);

    // Unified API with useContiguousMemory = true
    $params = ZVecIndexParams::forHnsw(
        metricType: ZVecSchema::METRIC_COSINE,
        m: 16,
        efConstruction: 200,
        quantizeType: ZVec::QUANTIZE_UNDEFINED,
        useContiguousMemory: true
    );
    $coll->createIndex('vec', $params);
    echo "HNSW with contiguous memory via unified API\n";

    // Insert and query
    $coll->insert(
        (new ZVecDoc('d1'))->setVectorFp32('vec', [1.0, 0.0, 0.0, 0.0]),
        (new ZVecDoc('d2'))->setVectorFp32('vec', [0.0, 1.0, 0.0, 0.0])
    );
    $coll->optimize();
    $results = $coll->query('vec', [1.0, 0.1, 0.0, 0.0], topk: 3);
    echo "Query returned " . count($results) . " results\n";

    // UseContiguousMemory = false (default) via unified API
    $coll->dropIndex('vec');
    $params2 = ZVecIndexParams::forHnsw(
        metricType: ZVecSchema::METRIC_COSINE,
        m: 16,
        efConstruction: 200,
        useContiguousMemory: false
    );
    $coll->createIndex('vec', $params2);
    echo "HNSW without contiguous memory via unified API\n";

    // Legacy deprecated API with useContiguousMemory
    $coll->dropIndex('vec');
    $coll->createHnswIndex('vec', metricType: ZVecSchema::METRIC_COSINE, m: 16, efConstruction: 200, useContiguousMemory: true);
    echo "HNSW with contiguous memory via legacy API\n";

    $coll->close();
    echo "OK\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
HNSW with contiguous memory via unified API
Query returned 2 results
HNSW without contiguous memory via unified API
HNSW with contiguous memory via legacy API
OK
