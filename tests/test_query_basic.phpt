--TEST--
Query operations: basic vector search
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/query_basic_' . uniqid();

try {
    // Create schema with vector field
    $schema = new ZVecSchema('query_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('category', nullable: true, withInvertIndex: true)
        ->addFloat('score', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 200);

    // Insert test documents with different vectors
    // Using IP (inner product): vectors pointing in similar directions score higher
    $docs = [
        ['doc1', 1, [1.0, 0.0, 0.0, 0.0], 'tech', 90.0],
        ['doc2', 2, [0.9, 0.1, 0.0, 0.0], 'tech', 85.0],
        ['doc3', 3, [0.5, 0.5, 0.0, 0.0], 'finance', 75.0],
        ['doc4', 4, [0.0, 1.0, 0.0, 0.0], 'finance', 70.0],
        ['doc5', 5, [-1.0, 0.0, 0.0, 0.0], 'tech', 60.0],
    ];

    foreach ($docs as $d) {
        $doc = new ZVecDoc($d[0]);
        $doc->setInt64('id', $d[1])
            ->setVectorFp32('embedding', $d[2])
            ->setString('category', $d[3])
            ->setFloat('score', $d[4]);
        $c->insert($doc);
    }
    echo "Inserted 5 documents\n";

    // Optimize to ensure index is built
    $c->optimize();
    echo "Optimized\n";

    // Test 1: Basic query with default topk (10)
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0]);
    assert(count($results) === 5, 'Should return all 5 docs with default topk=10');
    echo "Query with default topk OK\n";

    // Test 2: Query with custom topk
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 3);
    assert(count($results) === 3, 'Should return exactly topk=3 docs');
    echo "Query with topk=3 OK\n";

    // Test 3: Query with includeVector=true
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 1, includeVector: true);
    assert(count($results) === 1, 'Should return 1 result');
    $vector = $results[0]->getVectorFp32('embedding');
    assert($vector !== null, 'Vector should be included');
    assert(count($vector) === 4, 'Vector should have 4 dimensions');
    echo "Query with includeVector=true OK\n";

    // Test 4: Query with includeVector=false (default)
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 1, includeVector: false);
    assert(count($results) === 1, 'Should return 1 result');
    $vector = $results[0]->getVectorFp32('embedding');
    assert($vector === null, 'Vector should NOT be included');
    echo "Query with includeVector=false OK\n";

    // Test 5: Verify score ordering (descending for IP)
    // Query vector [1,0,0,0] - doc1 [1,0,0,0] should be first (dot product = 1.0)
    // doc2 [0.9,0.1,0,0] should be second (dot product = 0.9)
    $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 5, includeVector: true);
    assert($results[0]->getPk() === 'doc1', 'First result should be doc1 (exact match)');
    assert($results[1]->getPk() === 'doc2', 'Second should be doc2 (similar)');
    
    // Verify scores are descending
    $scores = array_map(fn($d) => $d->getScore(), $results);
    for ($i = 1; $i < count($scores); $i++) {
        assert($scores[$i] <= $scores[$i-1], 'Scores should be in descending order for IP metric');
    }
    echo "Score ordering (descending for IP) OK\n";

    // Test 6: Query with L2 metric (lower is better)
    $schemaL2 = new ZVecSchema('query_l2_test');
    $schemaL2->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addVectorFp32('vec', dimension: 2, metricType: ZVecSchema::METRIC_L2);

    $pathL2 = __DIR__ . '/../test_dbs/query_l2_' . uniqid();
    $cL2 = ZVec::create($pathL2, $schemaL2);
    $cL2->createHnswIndex('vec', metricType: ZVecSchema::METRIC_L2, m: 16, efConstruction: 100);

    // Insert points at (0,0), (1,0), (2,0), (3,0)
    for ($i = 0; $i < 4; $i++) {
        $doc = new ZVecDoc("point$i");
        $doc->setInt64('id', $i)
            ->setVectorFp32('vec', [$i * 1.0, 0.0]);
        $cL2->insert($doc);
    }
    $cL2->optimize();

    // Query from (0,0) - closest should be point0 (distance=0)
    $results = $cL2->query('vec', [0.0, 0.0], topk: 3, includeVector: true);
    assert($results[0]->getPk() === 'point0', 'First result should be closest (point0)');
    
    // For L2, scores should be in ascending order (lower distance = better)
    $scores = array_map(fn($d) => $d->getScore(), $results);
    for ($i = 1; $i < count($scores); $i++) {
        assert($scores[$i] >= $scores[$i-1], 'Scores should be in ascending order for L2 metric');
    }
    echo "Score ordering (ascending for L2) OK\n";

    $cL2->close();
    exec("rm -rf " . escapeshellarg($pathL2));

    $c->close();
    echo "PASS: Basic query operations work\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Inserted 5 documents
Optimized
Query with default topk OK
Query with topk=3 OK
Query with includeVector=true OK
Query with includeVector=false OK
Score ordering (descending for IP) OK
Score ordering (ascending for L2) OK
PASS: Basic query operations work
