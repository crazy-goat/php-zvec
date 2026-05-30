--TEST--
Memory leak: query with output fields does not leak C strings
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/mem_query_output_' . uniqid();

try {
    $schema = new ZVecSchema('mem_query_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false)
        ->addString('category', nullable: true)
        ->addFloat('score', nullable: true)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $c->createHnswIndex('embedding', metricType: ZVecSchema::METRIC_IP, m: 16, efConstruction: 100);

    // Insert 20 documents
    for ($i = 0; $i < 20; $i++) {
        $doc = new ZVecDoc("doc{$i}");
        $doc->setInt64('id', $i)
            ->setString('category', 'cat_' . ($i % 5))
            ->setFloat('score', (float)$i)
            ->setVectorFp32('embedding', [(float)$i, 1.0, 0.0, 0.0]);
        $c->insert($doc);
    }
    $c->optimize();

    $memBefore = memory_get_usage();

    // 50x query with output fields (exercises toCStringArray/freeCStringArray)
    for ($i = 0; $i < 50; $i++) {
        $results = $c->query(
            'embedding',
            [1.0, 0.0, 0.0, 0.0],
            topk: 5,
            outputFields: ['id', 'category', 'score']
        );
        assert(count($results) <= 5, 'Should return at most topk results');
    }

    // 50x query without output fields
    for ($i = 0; $i < 50; $i++) {
        $results = $c->query('embedding', [1.0, 0.0, 0.0, 0.0], topk: 5);
        assert(count($results) <= 5, 'Should return at most topk results');
    }

    $memAfter = memory_get_usage();
    $delta = $memAfter - $memBefore;

    // Allow 500KB tolerance
    if ($delta > 500 * 1024) {
        echo "FAIL: Memory grew by {$delta} bytes (threshold 500KB)\n";
        exit(1);
    }

    echo "100x query OK (delta: {$delta} bytes)\n";
    $c->close();
    echo "PASS: Query output fields do not leak C strings\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECTF--
100x query OK (delta: %d bytes)
PASS: Query output fields do not leak C strings
