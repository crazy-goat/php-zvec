--TEST--
Close vs destroy state tracking: distinct error messages for closed vs destroyed
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/close_vs_destroy_' . uniqid();

$schema = new ZVecSchema('state_test');
$schema->addInt64('id', nullable: false, withInvertIndex: true)
    ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);

// Insert a document
$doc = new ZVecDoc('doc1');
$doc->setInt64('id', 1)->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
$c->insert($doc);

// Test 1: Closed collection throws specific message
echo "Test 1: Closed collection error message\n";
$c->close();
try {
    $c->insert($doc);
    echo "FAIL: Should have thrown exception\n";
    exit(1);
} catch (ZVecException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'closed') !== false && strpos($msg, 'ZVec::open()') !== false) {
        echo "OK: Closed message is correct\n";
    } else {
        echo "FAIL: Wrong message for closed state: $msg\n";
        exit(1);
    }
}

// Test 2: Reopen and destroy
echo "Test 2: Destroyed collection error message\n";
$c2 = ZVec::open($path);
$c2->destroy();
try {
    $c2->insert($doc);
    echo "FAIL: Should have thrown exception\n";
    exit(1);
} catch (ZVecException $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'destroyed') !== false && strpos($msg, 'cannot be reused') !== false) {
        echo "OK: Destroyed message is correct\n";
    } else {
        echo "FAIL: Wrong message for destroyed state: $msg\n";
        exit(1);
    }
}

// Test 3: Close after destroy is a no-op
echo "Test 3: Close after destroy is safe\n";
try {
    $c2->close();  // Should be safe (no-op)
    echo "OK: close() after destroy() is safe\n";
} catch (Throwable $e) {
    echo "FAIL: close() after destroy() should not throw\n";
    exit(1);
}

// Test 4: Destroy after close works
echo "Test 4: Destroy after close works\n";
$c3 = ZVec::create($path . '_2', $schema);
$c3->close();
$c3->destroy();
echo "OK: destroy() after close() works\n";

// Test 5: Double destroy is idempotent
echo "Test 5: Double destroy is idempotent\n";
try {
    $c3->destroy();  // Should be safe (no-op)
    echo "OK: double destroy() is safe\n";
} catch (Throwable $e) {
    echo "FAIL: double destroy() should not throw\n";
    exit(1);
}

// Cleanup
exec("rm -rf " . escapeshellarg($path));
exec("rm -rf " . escapeshellarg($path . '_2'));

echo "All close vs destroy tests passed\n";
?>
--EXPECT--
Test 1: Closed collection error message
OK: Closed message is correct
Test 2: Destroyed collection error message
OK: Destroyed message is correct
Test 3: Close after destroy is safe
OK: close() after destroy() is safe
Test 4: Destroy after close works
OK: destroy() after close() works
Test 5: Double destroy is idempotent
OK: double destroy() is safe
All close vs destroy tests passed
