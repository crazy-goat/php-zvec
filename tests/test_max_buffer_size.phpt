--TEST--
max_buffer_size parameter: create, open, and retrieve options
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/max_buffer_test_' . uniqid();

// Default from C++: 64 * 1024 * 1024 = 67108864
$defaultBufferSize = 64 * 1024 * 1024;

try {
    // Test 1: Create collection with explicit default max_buffer_size
    $schema = new ZVecSchema('test');
    $schema->addInt64('id', false, true)
           ->addVectorFp32('vec', 4, ZVecSchema::METRIC_IP);
    
    $collection = ZVec::create($path, $schema, false, true, $defaultBufferSize);
    $opts = $collection->options();
    
    echo "Default max_buffer_size: " . $opts['max_buffer_size'] . "\n";
    assert($opts['max_buffer_size'] === $defaultBufferSize, "Default max_buffer_size should be 64MB");
    
    $collection->close();
    
    // Cleanup and recreate with custom max_buffer_size
    exec("rm -rf " . escapeshellarg($path));
    
    // Test 2: Create collection with custom max_buffer_size (32MB)
    $customBufferSize = 32 * 1024 * 1024; // 32MB
    $collection = ZVec::create($path, $schema, false, true, $customBufferSize);
    $opts = $collection->options();
    
    echo "Custom max_buffer_size: " . $opts['max_buffer_size'] . "\n";
    assert($opts['max_buffer_size'] === $customBufferSize, "Custom max_buffer_size should be 32MB");
    
    $collection->close();
    
    // Test 3: Open collection and verify max_buffer_size can be overridden
    // Note: max_buffer_size appears to be configurable at open time
    $collection2 = ZVec::open($path, false, true, $defaultBufferSize);
    $opts2 = $collection2->options();
    
    echo "Opened collection max_buffer_size: " . $opts2['max_buffer_size'] . "\n";
    assert($opts2['max_buffer_size'] === $defaultBufferSize, "Opened collection should use provided max_buffer_size");
    assert($opts2['read_only'] === false, "read_only should be false");
    assert($opts2['enable_mmap'] === true, "enable_mmap should be true");
    
    $collection2->close();
    
    echo "All max_buffer_size tests passed!\n";
    
} finally {
    exec("rm -rf " . escapeshellarg($path));
}
?>
--EXPECT--
Default max_buffer_size: 67108864
Custom max_buffer_size: 33554432
Opened collection max_buffer_size: 67108864
All max_buffer_size tests passed!
