--TEST--
Index ops: createIndex on already-indexed field behavior
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/idx_already_' . uniqid();
try {
    $schema = new ZVecSchema('test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    $c->insert(
        (new ZVecDoc('d1'))->setInt64('id', 1)->setVectorFp32('v', [1.0, 0.0, 0.0, 0.0]),
        (new ZVecDoc('d2'))->setInt64('id', 2)->setVectorFp32('v', [0.0, 1.0, 0.0, 0.0])
    );
    $c->optimize();

    // Create first index — should succeed
    $params = ZVecIndexParams::forHnsw(
        metricType: ZVecSchema::METRIC_IP,
        m: 16,
        efConstruction: 200
    );
    $c->createIndex('v', $params);
    echo "First HNSW index created OK\n";

    // Query works with first index
    $results = $c->query('v', [1.0, 0.0, 0.0, 0.0], topk: 2);
    assert(count($results) === 2, 'Expected 2 results');
    echo "Query with first index returned " . count($results) . " results OK\n";

    // Create second index on same field — may succeed (replaces) or throw
    try {
        $params2 = ZVecIndexParams::forFlat(
            metricType: ZVecSchema::METRIC_IP
        );
        $c->createIndex('v', $params2);
        echo "Second index (Flat) created — field supports index replacement\n";

        // Verify query still works after index replacement
        $results2 = $c->query('v', [1.0, 0.0, 0.0, 0.0], topk: 2);
        assert(count($results2) === 2, 'Expected 2 results after index replacement');
        echo "Query after index replacement returned " . count($results2) . " results OK\n";
    } catch (ZVecException $e) {
        echo "Index creation on already-indexed field rejected: " . $e->getMessage() . "\n";
    }

    $c->close();
    echo "PASS: createIndex on already-indexed field handled correctly\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
First HNSW index created OK
Query with first index returned 2 results OK
Second index (Flat) created — field supports index replacement
Query after index replacement returned 2 results OK
PASS: createIndex on already-indexed field handled correctly
