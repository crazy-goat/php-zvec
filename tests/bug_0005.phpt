--TEST--
Bug 0005: Cleanup failure after failed insert causes segfault or lock errors (FIXED)
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/cleanup_fail_' . uniqid();

try {
    // Create schema with required field
    $schema = new ZVecSchema('test_cleanup');
    $schema->addInt64('id', nullable: false);
    $schema->addVectorFp32('vec', dimension: 4);
    
    // Test 1: Failed insert then close
    $c = ZVec::create($path, $schema);
    
    // Try to insert doc WITHOUT required 'id' field - should fail
    $doc = new ZVecDoc('doc1');
    $doc->setVectorFp32('vec', [1.0, 2.0, 3.0, 4.0]);
    
    try {
        $c->insert($doc);
        echo "FAIL: Expected exception was not thrown\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "Test 1: Got expected exception\n";
    }
    
    // Close should work without segfault
    $c->close();
    echo "Test 1: Closed successfully\n";
    
    exec("rm -rf " . escapeshellarg($path));
    
    // Test 2: Failed insert then destroy
    $c2 = ZVec::create($path, $schema);
    
    $doc2 = new ZVecDoc('doc2');
    $doc2->setVectorFp32('vec', [5.0, 6.0, 7.0, 8.0]);
    
    try {
        $c2->insert($doc2);
    } catch (ZVecException $e) {
        echo "Test 2: Got expected exception\n";
    }
    
    // Destroy should work without issues
    $c2->destroy();
    echo "Test 2: Destroyed successfully\n";
    
    echo "PASS: bug_0005 - Cleanup after failed ops works correctly\n";
    
} catch (Throwable $e) {
    echo "FAIL: Unexpected error: " . get_class($e) . ": " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Test 1: Got expected exception
Test 1: Closed successfully
Test 2: Got expected exception
Test 2: Destroyed successfully
PASS: bug_0005 - Cleanup after failed ops works correctly
