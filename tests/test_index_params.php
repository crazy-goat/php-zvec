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

    // Vamana via unified API (only unified API, no legacy wrapper)
    $params5 = ZVecIndexParams::forVamana(
        metricType: ZVecSchema::METRIC_COSINE,
        maxDegree: 32,
        searchListSize: 50,
        alpha: 1.0,
        saturateGraph: false,
        quantizeType: ZVec::QUANTIZE_UNDEFINED
    );
    $coll->createIndex('vec', $params5);
    echo "Vamana via unified API\n";

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
