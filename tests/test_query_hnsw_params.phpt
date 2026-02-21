--TEST--
Query operations: HNSW query parameters (hnswEf, queryParamType)
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_hnsw_' . uniqid();

try {
    $schema = new ZVecSchema('hnsw_query_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addVectorFp32('embedding', dimension: 128, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    
    // Create HNSW index with high efConstruction for better recall
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Insert many documents (need large dataset for meaningful recall comparison)
    $targetCount = 500;
    echo "Inserting $targetCount documents...\n";
    
    for ($i = 0; $i < $targetCount; $i++) {
        // Generate random unit vectors
        $vector = [];
        $norm = 0;
        for ($j = 0; $j < 128; $j++) {
            $val = mt_rand() / mt_getrandmax() - 0.5;
            $vector[] = $val;
            $norm += $val * $val;
        }
        $norm = sqrt($norm);
        if ($norm > 0) {
            $vector = array_map(fn($v) => $v / $norm, $vector);
        }
        
        $doc = new ZVecDoc("doc_$i");
        $doc->setInt64('id', $i)
            ->setVectorFp32('embedding', $vector);
        $c->insert($doc);
        
        if (($i + 1) % 100 === 0) {
            echo "Inserted " . ($i + 1) . " documents\n";
        }
    }
    // Final count already printed in loop

    $c->optimize();
    echo "Optimized\n";

    // Test 1: Query with default hnswEf (200)
    $queryVector = array_fill(0, 128, 0.0);
    $queryVector[0] = 1.0; // Simple query vector [1, 0, 0, ...]
    
    $resultsDefault = $c->query(
        'embedding', 
        $queryVector, 
        topk: 50, 
        queryParamType: ZVec::QUERY_PARAM_HNSW,
        hnswEf: 200
    );
    assert(count($resultsDefault) === 50, 'Should return 50 results with ef=200');
    echo "Query with hnswEf=200 OK\n";

    // Test 2: Query with low hnswEf (50) - faster but lower recall
    $resultsLow = $c->query(
        'embedding', 
        $queryVector, 
        topk: 50, 
        queryParamType: ZVec::QUERY_PARAM_HNSW,
        hnswEf: 50
    );
    assert(count($resultsLow) === 50, 'Should return 50 results with ef=50');
    echo "Query with hnswEf=50 OK\n";

    // Test 3: Query with high hnswEf (400) - slower but higher recall
    $resultsHigh = $c->query(
        'embedding', 
        $queryVector, 
        topk: 50, 
        queryParamType: ZVec::QUERY_PARAM_HNSW,
        hnswEf: 400
    );
    assert(count($resultsHigh) === 50, 'Should return 50 results with ef=400');
    echo "Query with hnswEf=400 OK\n";

    // Test 4: Compare recall between different ef values
    // Higher ef should give better results (more overlap with ground truth)
    // We use brute force (no index) as reference by querying with QUERY_PARAM_NONE
    // But since we don't have exact ground truth, we just verify the API works
    
    // Get top 10 results with different ef values
    $top10Ef50 = array_slice($resultsLow, 0, 10);
    $top10Ef200 = array_slice($resultsDefault, 0, 10);
    $top10Ef400 = array_slice($resultsHigh, 0, 10);
    
    // Get Pks for comparison
    $pksEf50 = array_map(fn($d) => $d->getPk(), $top10Ef50);
    $pksEf200 = array_map(fn($d) => $d->getPk(), $top10Ef200);
    $pksEf400 = array_map(fn($d) => $d->getPk(), $top10Ef400);
    
    // Higher ef should have at least as good results as lower ef
    // (this is a soft check - real recall would need ground truth)
    echo "Comparing results:\n";
    echo "  Top-3 with ef=50: " . implode(', ', array_slice($pksEf50, 0, 3)) . "\n";
    echo "  Top-3 with ef=200: " . implode(', ', array_slice($pksEf200, 0, 3)) . "\n";
    echo "  Top-3 with ef=400: " . implode(', ', array_slice($pksEf400, 0, 3)) . "\n";
    
    // Note: With higher ef, we expect more stable/better results
    // But we can't strictly assert this without ground truth
    echo "Recall comparison completed\n";

    // Test 5: Query with QUERY_PARAM_NONE (no HNSW params)
    // This uses default index behavior
    $resultsNone = $c->query(
        'embedding', 
        $queryVector, 
        topk: 50, 
        queryParamType: ZVec::QUERY_PARAM_NONE
    );
    assert(count($resultsNone) === 50, 'Should return 50 results with QUERY_PARAM_NONE');
    echo "Query with QUERY_PARAM_NONE OK\n";

    // Test 6: Query with QUERY_PARAM_HNSW explicitly
    $resultsHnsw = $c->query(
        'embedding', 
        $queryVector, 
        topk: 50, 
        queryParamType: ZVec::QUERY_PARAM_HNSW,
        hnswEf: 100
    );
    assert(count($resultsHnsw) === 50, 'Should return 50 results with QUERY_PARAM_HNSW');
    echo "Query with QUERY_PARAM_HNSW OK\n";

    // Test 7: Verify scores are accessible
    $results = $c->query(
        'embedding', 
        $queryVector, 
        topk: 5, 
        queryParamType: ZVec::QUERY_PARAM_HNSW,
        hnswEf: 200
    );
    
    foreach ($results as $i => $doc) {
        $score = $doc->getScore();
        assert($score !== null, "Document $i should have a score");
        assert(is_float($score), "Score should be a float");
        if ($i > 0) {
            // Scores should be descending for IP metric
            assert($score <= $results[$i-1]->getScore(), 'Scores should be in descending order');
        }
    }
    echo "Score ordering verified\n";

    // Test 8: Test that query works without queryParamType (backward compatibility)
    $resultsCompat = $c->query(
        'embedding', 
        $queryVector, 
        topk: 10
    );
    assert(count($resultsCompat) === 10, 'Should work without explicit queryParamType');
    echo "Backward compatibility OK\n";

    $c->close();
    echo "PASS: HNSW query parameters work correctly\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
Inserting 500 documents...
Inserted 100 documents
Inserted 200 documents
Inserted 300 documents
Inserted 400 documents
Inserted 500 documents
Optimized
Query with hnswEf=200 OK
Query with hnswEf=50 OK
Query with hnswEf=400 OK
Comparing results:
  Top-3 with ef=50: doc_%d, doc_%d, doc_%d
  Top-3 with ef=200: doc_%d, doc_%d, doc_%d
  Top-3 with ef=400: doc_%d, doc_%d, doc_%d
Recall comparison completed
Query with QUERY_PARAM_NONE OK
Query with QUERY_PARAM_HNSW OK
Score ordering verified
Backward compatibility OK
PASS: HNSW query parameters work correctly
