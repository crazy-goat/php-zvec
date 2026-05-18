<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/fp64_' . uniqid();

$schema = new ZVecSchema('fp64_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addVectorFp64('v', dimension: 4, metricType: ZVecSchema::METRIC_COSINE);

$c = ZVec::create($path, $schema);

try {
    // Test 1: Insert docs with FP64 vectors
    $docs = [];
    foreach ([
        'doc1' => [0.1, 0.2, 0.3, 0.4],
        'doc2' => [0.5, 0.6, 0.7, 0.8],
        'doc3' => [0.9, 0.1, 0.2, 0.3],
    ] as $pk => $vec) {
        $doc = new ZVecDoc($pk);
        $doc->setInt64('id', (int)substr($pk, 3))
            ->setVectorFp64('v', $vec);
        $docs[] = $doc;
    }
    $c->insert(...$docs);
    $c->optimize();
    echo "Inserted 3 FP64 docs OK\n";

    // Test 2: Fetch and verify FP64 vectors
    $fetched = $c->fetch('doc1', 'doc2', 'doc3');
    assert(count($fetched) === 3, 'Expected 3 docs');

    $v = $fetched[0]->getVectorFp64('v');
    assert($v !== null, 'Expected FP64 vector');
    assert(count($v) === 4, 'Expected dimension 4');
    assert(abs($v[0] - 0.1) < 1e-10, "Expected 0.1, got {$v[0]}");
    assert(abs($v[3] - 0.4) < 1e-10, "Expected 0.4, got {$v[3]}");
    echo "Fetched FP64 vectors OK\n";

    // Test 3: getVectorFp32 returns null for FP64 field
    $nullVec = $fetched[0]->getVectorFp32('v');
    assert($nullVec === null, 'Expected null from getVectorFp32 on FP64 field');
    echo "getVectorFp32 returns null for FP64 field OK\n";

    // Test 4: Query with FP64 vector
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 3);
    assert(count($results) === 3, 'Expected 3 results');
    assert($results[0]->getPk() === 'doc1', 'Expected doc1 as top result');
    assert(abs($results[0]->getScore() - 1.0) < 0.001, 'Expected score≈1.0 for identical vector');
    echo "FP64 query returned correct results OK\n";

    // Test 5: queryFp64 with includeVector
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 1, includeVector: true);
    assert(count($results) === 1, 'Expected 1 result');
    $v = $results[0]->getVectorFp64('v');
    assert($v !== null, 'Expected vector with includeVector');
    assert(abs($v[0] - 0.1) < 1e-10, 'Expected correct vector data');
    echo "FP64 query with includeVector OK\n";

    // Test 6: queryFp64 with filter
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 3, filter: 'id > 1');
    assert(count($results) === 2, 'Expected 2 results after filter');
    echo "FP64 query with filter OK\n";

    // Test 7: queryFp64 with outputFields
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 1, outputFields: ['id']);
    assert(count($results) === 1, 'Expected 1 result');
    assert($results[0]->getInt64('id') === 1, 'Expected id=1');
    echo "FP64 query with outputFields OK\n";

    // Test 8: Query via ZVecVectorQuery with useFp64
    $vq = new ZVecVectorQuery('v', [0.5, 0.6, 0.7, 0.8]);
    $vq->setFp64(true);
    $results = $c->query($vq, topk: 3);
    assert(count($results) === 3, 'Expected 3 results');
    assert($results[0]->getPk() === 'doc2', 'Expected doc2 as top result');
    echo "ZVecVectorQuery with useFp64 OK\n";

    // Test 9: Query by ID with FP64
    $results = $c->queryById('v', 'doc1', topk: 3);
    assert(count($results) === 3, 'Expected 3 results');
    assert($results[0]->getPk() === 'doc1', 'Expected doc1 as top result');
    echo "queryById with FP64 OK\n";

    // Test 10: hasVector and vectorNames with FP64
    assert($fetched[0]->hasVector('v'), 'Expected hasVector true for FP64 field');
    assert(!$fetched[0]->hasVector('nonexistent'), 'Expected hasVector false for non-existent field');
    $vecNames = $fetched[0]->vectorNames();
    assert(in_array('v', $vecNames), 'Expected v in vectorNames');
    $fieldNames = $fetched[0]->fieldNames();
    assert(!in_array('v', $fieldNames), 'Expected v NOT in fieldNames');
    echo "hasVector/vectorNames/fieldNames with FP64 OK\n";

    // Test 11: Create HNSW index on FP64 field and query
    $c->createHnswIndex('v', metricType: ZVec::METRIC_COSINE, m: 16, efConstruction: 200);
    $c->optimize();
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 3);
    assert(count($results) === 3, 'Expected 3 results');
    assert($results[0]->getPk() === 'doc1', 'Expected doc1 as top result');
    echo "HNSW index on FP64 field OK\n";

    // Test 12: queryFp64 with HNSW params
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 3,
        queryParamType: ZVec::QUERY_PARAM_HNSW, hnswEf: 100);
    assert(count($results) === 3, 'Expected 3 results');
    echo "FP64 query with HNSW params OK\n";

    // Test 13: Create Flat index on FP64 field
    $c->dropIndex('v');
    $c->createFlatIndex('v', metricType: ZVec::METRIC_COSINE);
    $c->optimize();
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 3,
        queryParamType: ZVec::QUERY_PARAM_FLAT);
    assert(count($results) === 3, 'Expected 3 results');
    assert($results[0]->getPk() === 'doc1', 'Expected doc1 as top result');
    echo "Flat index on FP64 field OK\n";

    echo "ALL TESTS PASSED\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
