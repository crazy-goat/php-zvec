--TEST--
Closed collection protection: operations throw exception instead of segfault
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_closed_protection_' . uniqid();

$schema = new ZVecSchema('closed_test');
$schema->addInt64('id', nullable: false, withInvertIndex: true)
    ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);

// Insert a document
$doc = new ZVecDoc('doc1');
$doc->setInt64('id', 1)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
$c->insert($doc);

// Close the collection
$c->close();

// Test: Operations on closed collection should throw ZVecException, not segfault
echo "Testing insert on closed collection...\n";
try {
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc2);
    echo "FAIL: Should have thrown exception\n";
    exit(1);
} catch (ZVecException $e) {
    echo "OK: insert - " . $e->getMessage() . "\n";
}

echo "Testing query on closed collection...\n";
try {
    $c->query('v', [0.1, 0.2, 0.3, 0.4], topk: 5);
    echo "FAIL: Should have thrown exception\n";
    exit(1);
} catch (ZVecException $e) {
    echo "OK: query - " . $e->getMessage() . "\n";
}

echo "Testing stats on closed collection...\n";
try {
    $c->stats();
    echo "FAIL: Should have thrown exception\n";
    exit(1);
} catch (ZVecException $e) {
    echo "OK: stats - " . $e->getMessage() . "\n";
}

echo "Testing double close...\n";
try {
    $c->close();  // Should be safe (no-op)
    echo "OK: double close is safe\n";
} catch (Throwable $e) {
    echo "FAIL: Double close should not throw\n";
    exit(1);
}

// Cleanup
exec("rm -rf " . escapeshellarg($path));

echo "All closed collection operations handled correctly\n";
?>
--EXPECT--
Testing insert on closed collection...
OK: insert - Collection is closed or destroyed
Testing query on closed collection...
OK: query - Collection is closed or destroyed
Testing stats on closed collection...
OK: stats - Collection is closed or destroyed
Testing double close...
OK: double close is safe
All closed collection operations handled correctly
