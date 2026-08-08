--TEST--
Quantizer: random rotation (setQuantizerEnableRotate) for INT8/INT4 quantized indexes
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/quantize_rotate_' . uniqid();
try {
    $schema = new ZVecSchema('rotate_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 8, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    for ($i = 1; $i <= 20; $i++) {
        $vec = [];
        for ($j = 0; $j < 8; $j++) {
            $vec[] = 0.01 * $i + 0.001 * $j;
        }
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)
            ->setVectorFp32('v', $vec);
        $c->insert($doc);
    }
    $c->optimize();

    $queryVec = [];
    for ($j = 0; $j < 8; $j++) {
        $queryVec[] = 0.01 + 0.001 * $j;
    }

    // Baseline: non-quantized HNSW query results
    $baseline = $c->query('v', $queryVec, topk: 5);
    assert(count($baseline) === 5, "Expected 5 baseline results");
    $baselineIds = array_map(fn($r) => $r->getPk(), $baseline);

    // HNSW + QUANTIZE_INT8 + random rotation enabled
    $params = ZVecIndexParams::forHnsw(
        metricType: ZVecSchema::METRIC_IP,
        m: 16,
        efConstruction: 200,
        quantizeType: ZVec::QUANTIZE_INT8
    )->setQuantizerEnableRotate(true);
    $c->createIndex('v', $params);
    $c->flush();
    $c->optimize();
    echo "Created HNSW QUANTIZE_INT8 index with random rotation\n";

    $int8Rot = $c->query('v', $queryVec, topk: 5);
    assert(count($int8Rot) === 5, "Expected 5 INT8 rotated results");
    foreach ($int8Rot as $r) {
        assert(strpos($r->getPk(), 'doc') === 0, "Expected valid doc ID");
    }
    $common = count(array_intersect($baselineIds, array_map(fn($r) => $r->getPk(), $int8Rot)));
    assert($common >= 3, "Expected at least 3 common results with rotated INT8, got $common");
    echo "HNSW rotated INT8 query returns accurate results ($common/5 overlap)\n";

    // Flat + QUANTIZE_INT8 + rotation disabled (default) still skips rotation
    $c->dropIndex('v');
    $c->flush();
    $params2 = ZVecIndexParams::forFlat(
        metricType: ZVecSchema::METRIC_IP,
        quantizeType: ZVec::QUANTIZE_INT8
    );
    $c->createIndex('v', $params2);
    $c->flush();
    $c->optimize();
    echo "Created Flat QUANTIZE_INT8 index without rotation\n";

    $flatNoRot = $c->query('v', $queryVec, topk: 5);
    assert(count($flatNoRot) === 5, "Expected 5 Flat INT8 results");
    echo "Flat QUANTIZE_INT8 query without rotation works\n";

    // Flat + QUANTIZE_INT8 + rotation enabled
    $c->dropIndex('v');
    $c->flush();
    $params3 = ZVecIndexParams::forFlat(
        metricType: ZVecSchema::METRIC_IP,
        quantizeType: ZVec::QUANTIZE_INT8
    )->setQuantizerEnableRotate(true);
    $c->createIndex('v', $params3);
    $c->flush();
    $c->optimize();
    echo "Created Flat QUANTIZE_INT8 index with random rotation\n";

    $flatRot = $c->query('v', $queryVec, topk: 5);
    assert(count($flatRot) === 5, "Expected 5 rotated Flat INT8 results");
    foreach ($flatRot as $r) {
        assert(strpos($r->getPk(), 'doc') === 0, "Expected valid doc ID");
    }
    echo "Rotated Flat INT8 query returns valid results\n";

    // IVF + QUANTIZE_INT8 + rotation enabled
    $params4 = ZVecIndexParams::forIvf(
        metricType: ZVecSchema::METRIC_IP,
        nList: 4,
        nIters: 5,
        quantizeType: ZVec::QUANTIZE_INT8
    )->setQuantizerEnableRotate(true);
    $c->createIndex('v', $params4);
    $c->flush();
    $c->optimize();
    echo "Created IVF QUANTIZE_INT8 index with random rotation\n";

    $ivfRot = $c->query('v', $queryVec, topk: 5);
    assert(count($ivfRot) === 5, "Expected 5 rotated IVF INT8 results");
    echo "Rotated IVF INT8 query returns valid results\n";

    // Vamana + QUANTIZE_INT8 + rotation enabled
    $c->dropIndex('v');
    $c->flush();
    $params6 = ZVecIndexParams::forVamana(
        metricType: ZVecSchema::METRIC_IP,
        maxDegree: 32,
        searchListSize: 50,
        alpha: 1.0,
        quantizeType: ZVec::QUANTIZE_INT8
    )->setQuantizerEnableRotate(true);
    $c->createIndex('v', $params6);
    $c->flush();
    $c->optimize();
    echo "Created Vamana QUANTIZE_INT8 index with random rotation\n";

    $vamanaRot = $c->query('v', $queryVec, topk: 5);
    assert(count($vamanaRot) === 5, "Expected 5 rotated Vamana INT8 results");
    echo "Rotated Vamana INT8 query returns valid results\n";

    // Rotation flag is a no-op on non-quantized index (still works)
    $c->dropIndex('v');
    $c->flush();
    $params5 = ZVecIndexParams::forHnsw(
        metricType: ZVecSchema::METRIC_IP,
        m: 16,
        efConstruction: 200,
        quantizeType: ZVec::QUANTIZE_UNDEFINED
    )->setQuantizerEnableRotate(true);
    $c->createIndex('v', $params5);
    $c->flush();
    $c->optimize();
    echo "Created HNSW QUANTIZE_UNDEFINED index with rotation flag (no-op)\n";

    $noQuant = $c->query('v', $queryVec, topk: 5);
    assert(count($noQuant) === 5, "Expected 5 results from non-quantized index");
    echo "Non-quantized index with rotation flag works\n";

    $c->close();
    echo "PASS: quantizer random rotation works\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Created HNSW QUANTIZE_INT8 index with random rotation
HNSW rotated INT8 query returns accurate results (5/5 overlap)
Created Flat QUANTIZE_INT8 index without rotation
Flat QUANTIZE_INT8 query without rotation works
Created Flat QUANTIZE_INT8 index with random rotation
Rotated Flat INT8 query returns valid results
Created IVF QUANTIZE_INT8 index with random rotation
Rotated IVF INT8 query returns valid results
Created Vamana QUANTIZE_INT8 index with random rotation
Rotated Vamana INT8 query returns valid results
Created HNSW QUANTIZE_UNDEFINED index with rotation flag (no-op)
Non-quantized index with rotation flag works
PASS: quantizer random rotation works
