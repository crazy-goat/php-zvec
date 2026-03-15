--TEST--
IVF Index Creation and Query Operations
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/ivf_index_' . uniqid();

try {
    // Create schema with vector field
    $schema = new ZVecSchema('ivf_index_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert test docs (need more data for IVF to work properly)
    for ($i = 1; $i <= 50; $i++) {
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)
            ->setVectorFp32('v', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i]);
        $c->insert($doc);
    }
    $c->optimize();

    // Test 1: Create IVF index
    $c->createIvfIndex(
        'v',
        metricType: ZVecSchema::METRIC_IP,
        nList: 10,
        nIters: 5,
        useSoar: false
    );
    $c->flush();
    $c->optimize();
    
    echo "Created IVF index on vector field\n";

    // Test 2: Query with IVF index
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 5,
        queryParamType: ZVec::QUERY_PARAM_IVF,
        ivfNprobe: 3
    );
    assert(count($results) === 5, "Expected 5 results with IVF index");
    foreach ($results as $r) {
        assert(strpos($r->getPk(), 'doc') === 0, "Expected valid doc ID with IVF index");
    }
    echo "Query with IVF index returns correct results\n";

    // Test 3: Drop IVF and switch to HNSW
    $c->dropIndex('v');
    $c->flush();
    
    $c->createHnswIndex(
        'v',
        metricType: ZVecSchema::METRIC_IP,
        m: 16,
        efConstruction: 200
    );
    $c->flush();
    $c->optimize();
    
    echo "Switched from IVF to HNSW index\n";

    // Test 4: Query with HNSW after switching
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 5,
        queryParamType: ZVec::QUERY_PARAM_HNSW,
        hnswEf: 200
    );
    assert(count($results) === 5, "Expected 5 results with HNSW after switching");
    echo "Query works after switching from IVF to HNSW\n";

    // Test 5: Switch back to IVF
    $c->dropIndex('v');
    $c->flush();
    
    $c->createIvfIndex(
        'v',
        metricType: ZVecSchema::METRIC_IP,
        nList: 20,
        nIters: 10,
        useSoar: true
    );
    $c->flush();
    $c->optimize();
    
    echo "Switched back to IVF index with SOAR enabled\n";

    // Test 6: Query with IVF and SOAR
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 5,
        queryParamType: ZVec::QUERY_PARAM_IVF,
        ivfNprobe: 5
    );
    assert(count($results) === 5, "Expected 5 results with IVF+SOAR");
    echo "Query works with IVF+SOAR\n";

    // Test 7: Create IVF with quantization
    $c->dropIndex('v');
    $c->flush();
    
    $c->createIvfIndex(
        'v',
        metricType: ZVecSchema::METRIC_IP,
        nList: 10,
        nIters: 5,
        useSoar: false,
        quantizeType: ZVec::QUANTIZE_INT8
    );
    $c->flush();
    $c->optimize();
    
    echo "Created IVF index with INT8 quantization\n";

    // Test 8: Query with quantized IVF
    $results = $c->query(
        'v',  [0.1, 0.2, 0.3, 0.4],
        topk: 5,
        queryParamType: ZVec::QUERY_PARAM_IVF,
        ivfNprobe: 3
    );
    assert(count($results) === 5, "Expected 5 results with quantized IVF");
    echo "Query works with quantized IVF\n";

    $c->close();
    
    echo "PASS: All IVF index operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Created IVF index on vector field
Query with IVF index returns correct results
Switched from IVF to HNSW index
Query works after switching from IVF to HNSW
Switched back to IVF index with SOAR enabled
Query works with IVF+SOAR
Created IVF index with INT8 quantization
Query works with quantized IVF
PASS: All IVF index operations work
