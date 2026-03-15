--TEST--
Quantized indexes: QUANTIZE_INT8, QUANTIZE_FP16 for HNSW and Flat
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

$path = __DIR__ . '/../test_dbs/index_quantized_' . uniqid();

try {
    $schema = new ZVecSchema('quantized_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 8, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Insert test docs with 8-dim vectors
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

    // Baseline query without quantization
    $queryVec = [];
    for ($j = 0; $j < 8; $j++) {
        $queryVec[] = 0.01 + 0.001 * $j;
    }
    
    $baselineResults = $c->query(
        'v',  $queryVec,
        topk: 5
    );
    assert(count($baselineResults) === 5, "Expected 5 baseline results");
    echo "Baseline query (no quantization) completed\n";

    // Test 1: Create HNSW with QUANTIZE_INT8
    $c->createHnswIndex(
        'v',
        metricType: ZVecSchema::METRIC_IP,
        m: 16,
        efConstruction: 200,
        quantizeType: ZVec::QUANTIZE_INT8
    );
    $c->flush();
    $c->optimize();
    
    echo "Created HNSW index with QUANTIZE_INT8\n";

    // Test 2: Query with INT8 quantized HNSW
    $int8Results = $c->query(
        'v',  $queryVec,
        topk: 5
    );
    assert(count($int8Results) === 5, "Expected 5 results with INT8 quantization");
    
    // Verify results are valid docs
    foreach ($int8Results as $r) {
        assert(strpos($r->getPk(), 'doc') === 0, "Expected valid doc ID");
    }
    echo "INT8 quantized HNSW query returns valid results\n";

    // Test 3: Drop and create HNSW with QUANTIZE_FP16
    $c->dropIndex('v');
    $c->flush();
    
    $c->createHnswIndex(
        'v',
        metricType: ZVecSchema::METRIC_IP,
        m: 16,
        efConstruction: 200,
        quantizeType: ZVec::QUANTIZE_FP16
    );
    $c->flush();
    $c->optimize();
    
    echo "Created HNSW index with QUANTIZE_FP16\n";

    // Test 4: Query with FP16 quantized HNSW
    $fp16Results = $c->query(
        'v',  $queryVec,
        topk: 5
    );
    assert(count($fp16Results) === 5, "Expected 5 results with FP16 quantization");
    
    foreach ($fp16Results as $r) {
        assert(strpos($r->getPk(), 'doc') === 0, "Expected valid doc ID");
    }
    echo "FP16 quantized HNSW query returns valid results\n";

    // Test 5: Create Flat index with QUANTIZE_INT8
    $c->dropIndex('v');
    $c->flush();
    
    $c->createFlatIndex(
        'v',
        metricType: ZVecSchema::METRIC_IP,
        quantizeType: ZVec::QUANTIZE_INT8
    );
    $c->flush();
    $c->optimize();
    
    echo "Created Flat index with QUANTIZE_INT8\n";

    // Test 6: Query with INT8 quantized Flat
    $flatInt8Results = $c->query(
        'v',  $queryVec,
        topk: 5
    );
    assert(count($flatInt8Results) === 5, "Expected 5 results with INT8 Flat");
    
    foreach ($flatInt8Results as $r) {
        assert(strpos($r->getPk(), 'doc') === 0, "Expected valid doc ID");
    }
    echo "INT8 quantized Flat index query returns valid results\n";

    // Test 7: Check index size stats if available
    $stats = $c->stats();
    if (isset($stats['index_size_bytes'])) {
        echo "Index size: {$stats['index_size_bytes']} bytes\n";
    }
    if (isset($stats['index_completeness'])) {
        echo "Index completeness: {$stats['index_completeness']}\n";
    }

    // Test 8: Test QUANTIZE_UNDEFINED (no quantization)
    $c->dropIndex('v');
    $c->flush();
    
    $c->createHnswIndex(
        'v',
        metricType: ZVecSchema::METRIC_IP,
        m: 16,
        efConstruction: 200,
        quantizeType: ZVec::QUANTIZE_UNDEFINED  // No quantization
    );
    $c->flush();
    $c->optimize();
    
    $noQuantResults = $c->query(
        'v',  $queryVec,
        topk: 5
    );
    assert(count($noQuantResults) === 5, "Expected 5 results without quantization");
    echo "HNSW with QUANTIZE_UNDEFINED (no quantization) works\n";

    // Test 9: Verify quantized results are similar to non-quantized
    // (they might not be identical due to precision loss, but should be close)
    $noQuantIds = array_map(fn($r) => $r->getPk(), $noQuantResults);
    $int8Ids = array_map(fn($r) => $r->getPk(), $int8Results);
    $commonResults = array_intersect($noQuantIds, $int8Ids);
    $commonCount = count($commonResults);
    echo "Common results between baseline and INT8: $commonCount/5\n";
    
    // At least 3 out of 5 should match (allowing for quantization noise)
    assert($commonCount >= 3, "Expected at least 3 common results between baseline and INT8");
    echo "Quantization maintains reasonable accuracy\n";

    $c->close();
    
    echo "PASS: All quantized index operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Baseline query (no quantization) completed
Created HNSW index with QUANTIZE_INT8
INT8 quantized HNSW query returns valid results
Created HNSW index with QUANTIZE_FP16
FP16 quantized HNSW query returns valid results
Created Flat index with QUANTIZE_INT8
INT8 quantized Flat index query returns valid results
HNSW with QUANTIZE_UNDEFINED (no quantization) works
Common results between baseline and INT8: 5/5
Quantization maintains reasonable accuracy
PASS: All quantized index operations work
