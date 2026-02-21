<?php
/**
 * Bug reproduction: Cleanup failure after failed insert causes segfault or lock errors
 * 
 * Expected: After failed insert, collection should close cleanly without segfault
 * Actual: segfault (exit code 139) or RocksDB lock errors when closing
 * 
 * Status: Known limitation / In progress
 * Location: ffi/zvec_ffi.cc - resource cleanup after failed operations
 */

require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_cleanup_fail_' . uniqid();

echo "Test 1: Failed insert then close...\n";

try {
    // Create collection with required field
    $schema = new ZVecSchema('test_cleanup');
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('vec', dimension: 4);
    
    $c = ZVec::create($path, $schema);
    
    // Try to insert doc WITHOUT required 'id' field - should fail
    $doc = new ZVecDoc('doc1');
    $doc->setVectorFp32('vec', [1.0, 2.0, 3.0, 4.0]);
    // Missing: $doc->setInt64('id', 1);
    
    try {
        $c->insert($doc);  // Should throw ZVecException
        echo "FAIL: Expected exception was not thrown\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "OK: Got expected exception: " . $e->getMessage() . "\n";
    }
    
    // Now try to close - this is where segfault or lock error happens
    echo "Attempting to close collection after failed insert...\n";
    $c->close();
    echo "OK: Collection closed successfully\n";
    
    // Cleanup directory
    echo "Cleaning up directory...\n";
    exec("rm -rf " . escapeshellarg($path));
    echo "OK: Directory cleaned up\n";
    
    echo "\nTest 2: Failed insert then destroy...\n";
    
    // Recreate for second test
    $c2 = ZVec::create($path, $schema);
    
    $doc2 = new ZVecDoc('doc2');
    $doc2->setVectorFp32('vec', [5.0, 6.0, 7.0, 8.0]);
    
    try {
        $c2->insert($doc2);  // Should fail - missing id
    } catch (ZVecException $e) {
        echo "OK: Got expected exception\n";
    }
    
    // Try destroy instead of close
    echo "Attempting to destroy collection after failed insert...\n";
    $c2->destroy();
    echo "OK: Collection destroyed successfully\n";
    
    echo "\n=== ALL TESTS PASSED ===\n";
    
} catch (Throwable $e) {
    echo "FAIL: Unexpected error: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "This demonstrates the cleanup bug - should be fixed in C++ layer\n";
    
    // Try to cleanup anyway
    if (is_dir($path)) {
        exec("rm -rf " . escapeshellarg($path));
    }
    
    exit(1);
}

exit(0);
