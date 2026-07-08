<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/ivf_soar_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert enough data for IVF to work
    for ($i = 1; $i <= 50; $i++) {
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)
            ->setVectorFp32('v', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i]);
        $c->insert($doc);
    }
    $c->optimize();

    // Test 1: Create IVF index with SOAR via unified API
    $params = ZVecIndexParams::forIvf(
        metricType: ZVecSchema::METRIC_IP,
        nList: 10,
        nIters: 5,
        useSoar: true
    );
    $c->createIndex('v', $params);
    $c->flush();
    $c->optimize();
    echo "Created IVF index with SOAR via unified API OK\n";

    // Test 2: Query with IVF+SOAR
    $results = $c->query(
        'v', [0.1, 0.2, 0.3, 0.4],
        topk: 5,
        queryParamType: ZVec::QUERY_PARAM_IVF,
        ivfNprobe: 3
    );
    assert(count($results) === 5, 'Expected 5 results with IVF+SOAR');
    echo "Query with IVF+SOAR returned 5 results OK\n";

    // Test 3: Drop and recreate without SOAR — compare
    $c->dropIndex('v');
    $params2 = ZVecIndexParams::forIvf(
        metricType: ZVecSchema::METRIC_IP,
        nList: 10,
        nIters: 5,
        useSoar: false
    );
    $c->createIndex('v', $params2);
    $c->flush();
    $c->optimize();
    echo "Recreated IVF without SOAR via unified API OK\n";

    // Test 4: Query with IVF without SOAR
    $results2 = $c->query(
        'v', [0.1, 0.2, 0.3, 0.4],
        topk: 5,
        queryParamType: ZVec::QUERY_PARAM_IVF,
        ivfNprobe: 3
    );
    assert(count($results2) === 5, 'Expected 5 results with IVF no SOAR');
    echo "Query with IVF no SOAR returned 5 results OK\n";

    $c->close();
    echo "PASS: IVF with useSoar=true via unified API works\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
