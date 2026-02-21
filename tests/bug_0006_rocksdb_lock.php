<?php
/**
 * Bug reproduction: RocksDB lock error when recreating collection after failed insert
 * 
 * Expected: Can create new collection at same path after cleanup
 * Actual: IO error: lock hold by current process
 * 
 * Status: Known limitation / In progress
 * Location: ffi/zvec_ffi.cc - resource cleanup after failed operations
 */

require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_rocksdb_lock_' . uniqid();

echo "Test: Create collection, fail insert, cleanup, recreate...\n";

// Create schema with required field
$schema = new ZVecSchema('lock_test');
$schema->addInt64('id', nullable: false);
$schema->addVectorFp32('vec', dimension: 4);

// First attempt: create and fail insert
$c1 = ZVec::create($path, $schema);
$doc = new ZVecDoc('doc1');
$doc->setVectorFp32('vec', [1.0, 2.0, 3.0, 4.0]);
// Missing required 'id' field

try {
    $c1->insert($doc);
    echo "FAIL: Should have thrown exception\n";
    exit(1);
} catch (ZVecException $e) {
    echo "OK: Failed as expected: " . $e->getMessage() . "\n";
}

// Try to close
$c1->close();
echo "OK: Closed collection\n";

// Cleanup directory
echo "Removing directory...\n";
exec("rm -rf " . escapeshellarg($path));
if (is_dir($path)) {
    echo "FAIL: Directory still exists after rm -rf\n";
    exit(1);
}
echo "OK: Directory removed\n";

// Second attempt: try to create at same path
sleep(1);  // Give OS time to release locks

echo "Attempting to recreate collection at same path...\n";
try {
    $c2 = ZVec::create($path, $schema);
    echo "OK: Successfully recreated collection\n";
    
    // Insert valid doc
    $doc2 = new ZVecDoc('doc2');
    $doc2->setInt64('id', 2);
    $doc2->setVectorFp32('vec', [5.0, 6.0, 7.0, 8.0]);
    $c2->insert($doc2);
    echo "OK: Inserted valid doc\n";
    
    $c2->destroy();
    echo "OK: Destroyed collection\n";
    
    exec("rm -rf " . escapeshellarg($path));
    echo "OK: Final cleanup\n";
    
} catch (ZVecException $e) {
    echo "FAIL: RocksDB lock error: " . $e->getMessage() . "\n";
    echo "This demonstrates the lock bug\n";
    exit(1);
}

echo "\n=== TEST PASSED - No lock issues ===\n";
exit(0);
