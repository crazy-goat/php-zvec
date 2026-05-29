--TEST--
Bug 0007: Use-After-Free in destroy() - $closed set before checkStatus, conditional erase in C++
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

// Test 1: Normal destroy() marks closed and subsequent calls throw
echo "Test 1: Normal destroy() marks closed\n";
$path = __DIR__ . '/../test_dbs/bug_0007_1_' . uniqid();
try {
    $schema = new ZVecSchema('bug0007');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path, $schema);
    $doc = new ZVecDoc('d1');
    $doc->setInt64('id', 1)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);

    $c->destroy();
    // $closed should be true now - calling any method should throw
    try {
        $c->stats();
        echo "FAIL: stats after destroy should throw\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "OK: stats after destroy throws: " . $e->getMessage() . "\n";
    }

    // Directory should be removed
    if (!is_dir($path)) {
        echo "OK: directory removed\n";
    } else {
        echo "FAIL: directory still exists\n";
        exit(1);
    }
} finally {
    if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));
}

// Test 2: Double destroy() is idempotent
echo "\nTest 2: Double destroy() is idempotent\n";
$path2 = __DIR__ . '/../test_dbs/bug_0007_2_' . uniqid();
try {
    $schema = new ZVecSchema('bug0007');
    $schema->setMaxDocCountPerSegment(1000)
        ->addInt64('id', nullable: false, withInvertIndex: true)
        ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path2, $schema);
    $doc = new ZVecDoc('d1');
    $doc->setInt64('id', 1)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);

    $c->destroy();
    // Second destroy() should be a no-op (idempotent)
    try {
        $c->destroy();
        echo "OK: double destroy is safe\n";
    } catch (Throwable $e) {
        echo "FAIL: double destroy should not throw: " . $e->getMessage() . "\n";
        exit(1);
    }
} finally {
    if (is_dir($path2)) exec("rm -rf " . escapeshellarg($path2));
}

echo "\nAll Bug 0007 tests passed\n";
?>
--EXPECTF--
Test 1: Normal destroy() marks closed
OK: stats after destroy throws: Collection has been destroyed and cannot be reused
OK: directory removed

Test 2: Double destroy() is idempotent
OK: double destroy is safe

All Bug 0007 tests passed
