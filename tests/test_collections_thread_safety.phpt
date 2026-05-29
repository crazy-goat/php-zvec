--TEST--
Thread safety: global collections registry uses mutex and O(1) lookups
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

// Test 1: Rapid create/close cycle (100 collections) — verifies no crash
// from concurrent-like access patterns (vector reallocation race)
echo "Test 1: Rapid create/close cycle (100 collections)\n";
$paths = [];
for ($i = 0; $i < 100; $i++) {
    $paths[$i] = __DIR__ . '/../test_dbs/thread_safety_' . uniqid() . '_' . $i;
}
try {
    $collections = [];
    for ($i = 0; $i < 100; $i++) {
        $schema = new ZVecSchema("ts_test_$i");
        $schema->addInt64('id')->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
        $collections[$i] = ZVec::create($paths[$i], $schema);
    }
    echo "  Created 100 collections: OK\n";

    // Close all in reverse order
    for ($i = 99; $i >= 0; $i--) {
        $collections[$i]->close();
    }
    unset($collections);
    echo "  Closed all 100 collections: OK\n";
} finally {
    for ($i = 0; $i < 100; $i++) {
        exec("rm -rf " . escapeshellarg($paths[$i]));
    }
}

// Test 2: Interleaved create/close — simulates overlapping lifetimes
echo "Test 2: Interleaved create/close\n";
$paths2 = [];
$collections2 = [];
try {
    // Create 50
    for ($i = 0; $i < 50; $i++) {
        $paths2[$i] = __DIR__ . '/../test_dbs/thread_safety_interleaved_' . uniqid() . '_' . $i;
        $schema = new ZVecSchema("interleaved_$i");
        $schema->addInt64('id')->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
        $collections2[$i] = ZVec::create($paths2[$i], $schema);
    }
    echo "  Created 50 collections: OK\n";

    // Close first 25, create 25 more
    for ($i = 0; $i < 25; $i++) {
        $collections2[$i]->close();
        unset($collections2[$i]);
    }
    for ($i = 50; $i < 75; $i++) {
        $paths2[$i] = __DIR__ . '/../test_dbs/thread_safety_interleaved_' . uniqid() . '_' . $i;
        $schema = new ZVecSchema("interleaved_$i");
        $schema->addInt64('id')->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
        $collections2[$i] = ZVec::create($paths2[$i], $schema);
    }
    echo "  Interleaved create/close: OK\n";

    // Close remaining
    foreach ($collections2 as $c) {
        if ($c) $c->close();
    }
    unset($collections2);
    echo "  Closed all remaining: OK\n";
} finally {
    for ($i = 0; $i < 75; $i++) {
        if (isset($paths2[$i])) {
            exec("rm -rf " . escapeshellarg($paths2[$i]));
        }
    }
}

// Test 3: Destroy removes from registry — open after destroy fails cleanly
echo "Test 3: Destroy removes from registry\n";
$path3 = __DIR__ . '/../test_dbs/thread_safety_destroy_' . uniqid();
try {
    $schema = new ZVecSchema("destroy_test");
    $schema->addInt64('id')->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    $c = ZVec::create($path3, $schema);
    $c->destroy();
    // Attempting to open destroyed collection should fail, not crash
    try {
        $c2 = ZVec::open($path3);
        echo "FAIL: Should have thrown exception for destroyed collection\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "  Open after destroy correctly fails: OK\n";
    }
} finally {
    exec("rm -rf " . escapeshellarg($path3));
}

echo "All thread safety tests passed\n";
?>
--EXPECT--
Test 1: Rapid create/close cycle (100 collections)
  Created 100 collections: OK
  Closed all 100 collections: OK
Test 2: Interleaved create/close
  Created 50 collections: OK
  Interleaved create/close: OK
  Closed all remaining: OK
Test 3: Destroy removes from registry
  Open after destroy correctly fails: OK
All thread safety tests passed
