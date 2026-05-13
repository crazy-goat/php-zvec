--TEST--
IndexParams: unified createIndex() with ZVecIndexParams (all 4 types)
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/idxparams_' . uniqid();
try {
    // Create schema
    $schema = new ZVecSchema('test');
    $schema->addInt64('id', nullable: false, withInvertIndex: true);
    $schema->addVectorFp32('vec', 4, ZVecSchema::METRIC_COSINE);
    $coll = ZVec::create($path, $schema);

    // HNSW via unified API
    $params = ZVecIndexParams::forHnsw(
        metricType: ZVecSchema::METRIC_COSINE,
        m: 16,
        efConstruction: 200,
        quantizeType: ZVec::QUANTIZE_INT8
    );
    $coll->createIndex('vec', $params);
    echo "HNSW via unified API\n";

    // Flat via unified API
    $params2 = ZVecIndexParams::forFlat(metricType: ZVecSchema::METRIC_IP);
    $coll->createIndex('vec', $params2);
    echo "Flat via unified API\n";

    // Invert via unified API
    $params3 = ZVecIndexParams::forInvert(enableRange: true, enableWildcard: false);
    $coll->createIndex('id', $params3);
    echo "Invert via unified API\n";

    // IVF via unified API
    $params4 = ZVecIndexParams::forIvf(
        metricType: ZVecSchema::METRIC_COSINE,
        nList: 64,
        nIters: 5,
        useSoar: false,
        quantizeType: ZVec::QUANTIZE_FP16
    );
    $coll->createIndex('vec', $params4);
    echo "IVF via unified API\n";

    // Deprecated wrappers still work
    $coll->createHnswIndex('vec', metricType: ZVecSchema::METRIC_COSINE, m: 16, efConstruction: 200);
    echo "HNSW via legacy API\n";

    $coll->createFlatIndex('vec', metricType: ZVecSchema::METRIC_IP);
    echo "Flat via legacy API\n";

    $coll->createInvertIndex('id');
    echo "Invert via legacy API\n";

    $coll->createIvfIndex('vec', metricType: ZVecSchema::METRIC_COSINE, nList: 64, nIters: 5);
    echo "IVF via legacy API\n";

    $coll->close();
    echo "OK\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
HNSW via unified API
Flat via unified API
Invert via unified API
IVF via unified API
HNSW via legacy API
Flat via legacy API
Invert via legacy API
IVF via legacy API
OK
