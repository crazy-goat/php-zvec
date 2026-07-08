--TEST--
VECTOR_FP64 query operations: queryFp64(), queryById, includeVector, filter, outputFields
--XFAIL--
zvec v0.4.0 does not support VECTOR_FP64 as a dense vector type (schema validation rejects it). Needs upstream change: add DataType::VECTOR_FP64 to support_dense_vector_type in schema.cc
--SKIPIF--
<?php
if (extension_loaded('zvec')) die('skip Native zvec extension loaded (use FFI)');
if (!extension_loaded('ffi')) die('skip FFI extension not available');
?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/fp64q_' . uniqid();

try {
    $schema = new ZVecSchema('fp64_query_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('cat', nullable: false, withInvertIndex: true)
        ->addVectorFp64('v', dimension: 4, metricType: ZVecSchema::METRIC_COSINE);

    $c = ZVec::create($path, $schema);

    // Test 1: Insert docs with FP64 vectors
    $docs = [];
    foreach ([
        'doc1' => [0.1, 0.2, 0.3, 0.4],
        'doc2' => [0.5, 0.6, 0.7, 0.8],
        'doc3' => [0.9, 0.1, 0.2, 0.3],
    ] as $pk => $vec) {
        $doc = new ZVecDoc($pk);
        $doc->setInt64('id', (int)substr($pk, 3))
            ->setString('cat', $pk === 'doc1' ? 'A' : 'B')
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

    // Test 3: queryFp64() basic
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 3);
    assert(count($results) === 3, 'Expected 3 results');
    assert($results[0]->getPk() === 'doc1', 'Expected doc1 as top result');
    echo "queryFp64 returned correct results OK\n";

    // Test 4: queryFp64 with includeVector
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 1, includeVector: true);
    assert(count($results) === 1, 'Expected 1 result');
    $v = $results[0]->getVectorFp64('v');
    assert($v !== null, 'Expected vector with includeVector');
    assert(abs($v[0] - 0.1) < 1e-10, 'Expected correct vector data');
    echo "queryFp64 with includeVector OK\n";

    // Test 5: queryFp64 with filter
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 3, filter: "cat = 'A'");
    assert(count($results) === 1, 'Expected 1 result after filter');
    assert($results[0]->getPk() === 'doc1', 'Expected doc1 after filter');
    echo "queryFp64 with filter OK\n";

    // Test 6: queryFp64 with outputFields
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 1, outputFields: ['id', 'cat']);
    assert(count($results) === 1, 'Expected 1 result');
    assert($results[0]->getInt64('id') === 1, 'Expected id=1');
    assert($results[0]->getString('cat') === 'A', 'Expected cat=A');
    echo "queryFp64 with outputFields OK\n";

    // Test 7: query() with FP64 via ZVecVectorQuery (useFp64)
    $vq = new ZVecVectorQuery('v', [0.5, 0.6, 0.7, 0.8]);
    $vq->setFp64(true);
    $results = $c->query($vq, topk: 3);
    assert(count($results) === 3, 'Expected 3 results');
    assert($results[0]->getPk() === 'doc2', 'Expected doc2 as top result');
    echo "query() with FP64 auto-detection via useFp64 OK\n";

    // Test 8: queryById with FP64
    $results = $c->queryById('v', 'doc1', topk: 3);
    assert(count($results) >= 1, 'Expected at least 1 result');
    assert($results[0]->getPk() === 'doc1', 'Expected doc1 as top result');
    echo "queryById with FP64 OK\n";

    // Test 9: queryFp64 with HNSW params
    $c->createHnswIndex('v', metricType: ZVec::METRIC_COSINE, m: 16, efConstruction: 200);
    $c->optimize();
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 3);
    assert(count($results) === 3, 'Expected 3 results');
    assert($results[0]->getPk() === 'doc1', 'Expected doc1 as top result');
    echo "FP64 query with HNSW index OK\n";

    // Test 10: queryFp64 with Flat index
    $c->dropIndex('v');
    $c->createFlatIndex('v', metricType: ZVec::METRIC_COSINE);
    $c->optimize();
    $results = $c->queryFp64('v', [0.1, 0.2, 0.3, 0.4], topk: 3);
    assert(count($results) === 3, 'Expected 3 results');
    assert($results[0]->getPk() === 'doc1', 'Expected doc1 as top result');
    echo "FP64 query with Flat index OK\n";

    echo "ALL TESTS PASSED\n";
} finally {
    if (isset($c)) { try { $c->destroy(); } catch (Exception $e) {} }
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 3 FP64 docs OK
Fetched FP64 vectors OK
queryFp64 returned correct results OK
queryFp64 with includeVector OK
queryFp64 with filter OK
queryFp64 with outputFields OK
query() with FP64 auto-detection via useFp64 OK
queryById with FP64 OK
FP64 query with HNSW index OK
FP64 query with Flat index OK
ALL TESTS PASSED
