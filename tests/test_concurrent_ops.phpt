--TEST--
Concurrent operations: sequence of inserts and queries
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/concurrent_' . uniqid();

try {
    $schema = new ZVecSchema('concurrent_test');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addString('name', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);

    // Test 1: Multiple inserts in sequence
    for ($i = 1; $i <= 50; $i++) {
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)
            ->setString('name', "name_$i")
            ->setVectorFp32('v', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i]);
        $c->insert($doc);
    }
    echo "Inserted 50 documents OK\n";

    $stats = $c->stats();
    if (strpos($stats, 'doc_count:50') === false) {
        echo "FAIL: Should have 50 documents\n";
        exit(1);
    }
    echo "Verified doc_count=50 OK\n";

    // Test 2: Insert + query interleaved
    for ($i = 51; $i <= 100; $i++) {
        $doc = new ZVecDoc("doc$i");
        $doc->setInt64('id', $i)
            ->setString('name', "name_$i")
            ->setVectorFp32('v', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i]);
        $c->insert($doc);
        
        if ($i % 10 === 0) {
            $results = $c->query('v', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i], topk: 5);
            if (count($results) === 0) {
                echo "FAIL: Query should return results after insert $i\n";
                exit(1);
            }
        }
    }
    echo "Inserted 50 more documents with interleaved queries OK\n";

    $stats = $c->stats();
    if (strpos($stats, 'doc_count:100') === false) {
        echo "FAIL: Should have 100 documents\n";
        exit(1);
    }
    echo "Verified doc_count=100 OK\n";

    $c->close();
} finally {
    exec("rm -rf " . escapeshellarg($path));
}

echo "Sequence operations work correctly\n";
?>
--EXPECT--
Inserted 50 documents OK
Verified doc_count=50 OK
Inserted 50 more documents with interleaved queries OK
Verified doc_count=100 OK
Sequence operations work correctly
