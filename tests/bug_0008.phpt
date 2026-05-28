--TEST--
Bug 0008: close() then destroy() does not destroy data (FIXED)
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$basePath = __DIR__ . '/../test_dbs/bug_0008_';

// ===================================================================
// Test 1: destroy() after close() removes the data directory
// ===================================================================
$path1 = $basePath . uniqid();
try {
    $schema = new ZVecSchema('test_bug8a');
    $schema->setMaxDocCountPerSegment(1000);
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path1, $schema);
    echo "Test 1: Collection created\n";

    // Insert a doc so data is written to disk
    $doc = new ZVecDoc('doc1');
    $doc->setInt64('id', 1);
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->flush();
    echo "Test 1: Doc inserted and flushed\n";

    // Close first, then destroy
    $c->close();
    echo "Test 1: Collection closed\n";

    clearstatcache();
    $dirExistsBefore = is_dir($path1);
    echo "Test 1: Directory exists before destroy: " . ($dirExistsBefore ? 'yes' : 'no') . "\n";

    $c->destroy();
    echo "Test 1: destroy() called after close() - no exception\n";

    clearstatcache();
    $dirExistsAfter = is_dir($path1);
    echo "Test 1: Directory exists after destroy: " . ($dirExistsAfter ? 'yes' : 'no') . "\n";

    if ($dirExistsAfter) {
        echo "FAIL: Directory still exists after destroy()\n";
        exit(1);
    }
    echo "Test 1 PASS: Directory was removed\n";
} finally {
    exec("rm -rf " . escapeshellarg($path1));
}

// ===================================================================
// Test 2: destroy() without prior close() still works
// ===================================================================
$path2 = $basePath . uniqid();
try {
    $schema = new ZVecSchema('test_bug8b');
    $schema->setMaxDocCountPerSegment(1000);
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path2, $schema);
    $doc = new ZVecDoc('doc2');
    $doc->setInt64('id', 2);
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->flush();
    echo "Test 2: Collection created and doc inserted\n";

    // Destroy without closing first
    $c->destroy();
    echo "Test 2: destroy() called without close() - no exception\n";

    clearstatcache();
    $dirExists = is_dir($path2);
    echo "Test 2: Directory exists after destroy: " . ($dirExists ? 'yes' : 'no') . "\n";

    if ($dirExists) {
        echo "FAIL: Directory still exists after destroy() without close()\n";
        exit(1);
    }
    echo "Test 2 PASS: Directory was removed\n";
} finally {
    exec("rm -rf " . escapeshellarg($path2));
}

// ===================================================================
// Test 3: destroy() is idempotent (calling destroy() twice is safe)
// ===================================================================
$path3 = $basePath . uniqid();
try {
    $schema = new ZVecSchema('test_bug8c');
    $schema->setMaxDocCountPerSegment(1000);
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path3, $schema);
    $doc = new ZVecDoc('doc3');
    $doc->setInt64('id', 3);
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->flush();
    echo "Test 3: Collection created\n";

    // destroy() twice
    $c->destroy();
    echo "Test 3: First destroy() ok\n";

    try {
        $c->destroy();
        echo "Test 3: Second destroy() ok (no exception - idempotent)\n";
    } catch (ZVecException $e) {
        echo "FAIL: Second destroy() threw: " . $e->getMessage() . "\n";
        exit(1);
    }

    echo "Test 3 PASS: destroy() is idempotent\n";
} finally {
    exec("rm -rf " . escapeshellarg($path3));
}

// ===================================================================
// Test 4: close() after destroy() is a no-op (safe)
// ===================================================================
$path4 = $basePath . uniqid();
try {
    $schema = new ZVecSchema('test_bug8d');
    $schema->setMaxDocCountPerSegment(1000);
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path4, $schema);
    $doc = new ZVecDoc('doc4');
    $doc->setInt64('id', 4);
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->flush();
    echo "Test 4: Collection created\n";

    // destroy then close
    $c->destroy();
    echo "Test 4: destroy() ok\n";

    try {
        $c->close();
        echo "Test 4: close() after destroy() ok (no exception - safe no-op)\n";
    } catch (ZVecException $e) {
        echo "FAIL: close() after destroy() threw: " . $e->getMessage() . "\n";
        exit(1);
    }

    echo "Test 4 PASS: close() after destroy() is safe\n";
} finally {
    exec("rm -rf " . escapeshellarg($path4));
}

// ===================================================================
// Test 5: close() → destroy() → close() → destroy() — all safe
// ===================================================================
$path5 = $basePath . uniqid();
try {
    $schema = new ZVecSchema('test_bug8e');
    $schema->setMaxDocCountPerSegment(1000);
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path5, $schema);
    $doc = new ZVecDoc('doc5');
    $doc->setInt64('id', 5);
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    $c->flush();
    echo "Test 5: Collection created\n";

    $c->close();
    echo "Test 5: close() ok\n";

    $c->destroy();
    echo "Test 5: destroy() after close() ok\n";

    $c->close();
    echo "Test 5: close() after destroy() ok\n";

    $c->destroy();
    echo "Test 5: second destroy() after close() ok\n";

    clearstatcache();
    if (is_dir($path5)) {
        echo "FAIL: Directory still exists after all operations\n";
        exit(1);
    }
    echo "Test 5 PASS: All close/destroy sequences safe and data destroyed\n";
} finally {
    exec("rm -rf " . escapeshellarg($path5));
}

// ===================================================================
// Test 6: No data corruption — query works on normal path
// ===================================================================
$path6 = $basePath . uniqid();
try {
    $schema = new ZVecSchema('test_bug8f');
    $schema->setMaxDocCountPerSegment(1000);
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

    $c = ZVec::create($path6, $schema);
    echo "Test 6: Collection created\n";

    for ($i = 0; $i < 3; $i++) {
        $doc = new ZVecDoc("doc_$i");
        $doc->setInt64('id', $i);
        $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
        $c->insert($doc);
    }
    $c->flush();
    $c->optimize();

    // Normal query works
    $docs = $c->query('v', [0.1, 0.2, 0.3, 0.4], topk: 3);
    echo "Test 6: Query returned " . count($docs) . " docs\n";

    if (count($docs) !== 3) {
        echo "FAIL: Expected 3 docs\n";
        exit(1);
    }

    $c->close();
    echo "Test 6: close() ok\n";

    $c->destroy();

    clearstatcache();
    if (is_dir($path6)) {
        echo "FAIL: Directory still exists\n";
        exit(1);
    }
    echo "Test 6 PASS: Normal lifecycle with close+destroy works correctly\n";
} finally {
    exec("rm -rf " . escapeshellarg($path6));
}

echo "\nAll BUG-008 tests passed!\n";
?>
--EXPECTF--
Test 1: Collection created
Test 1: Doc inserted and flushed
Test 1: Collection closed
Test 1: Directory exists before destroy: yes
Test 1: destroy() called after close() - no exception
Test 1: Directory exists after destroy: no
Test 1 PASS: Directory was removed
Test 2: Collection created and doc inserted
Test 2: destroy() called without close() - no exception
Test 2: Directory exists after destroy: no
Test 2 PASS: Directory was removed
Test 3: Collection created
Test 3: First destroy() ok
Test 3: Second destroy() ok (no exception - idempotent)
Test 3 PASS: destroy() is idempotent
Test 4: Collection created
Test 4: destroy() ok
Test 4: close() after destroy() ok (no exception - safe no-op)
Test 4 PASS: close() after destroy() is safe
Test 5: Collection created
Test 5: close() ok
Test 5: destroy() after close() ok
Test 5: close() after destroy() ok
Test 5: second destroy() after close() ok
Test 5 PASS: All close/destroy sequences safe and data destroyed
Test 6: Collection created
Test 6: Query returned 3 docs
Test 6: close() ok
Test 6 PASS: Normal lifecycle with close+destroy works correctly

All BUG-008 tests passed!
