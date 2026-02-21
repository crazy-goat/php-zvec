--TEST--
Error handling: exceptions, invalid parameters, closed collection ops
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

// Test 1: Exception catching and message verification
$schema = new ZVecSchema('error_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addVectorFp32('v', dimension: 4, metricType: ZVecSchema::METRIC_IP);

try {
    $invalidPath = '/nonexistent_path/cannot_write_here';
    $c = ZVec::create($invalidPath, $schema);
    echo "FAIL: Exception should be thrown for invalid path\n";
    exit(1);
} catch (ZVecException $e) {
    $msg = $e->getMessage();
    $code = $e->getCode();
    if (strlen($msg) === 0 || !is_int($code)) {
        echo "FAIL: Exception should have message and integer code\n";
        exit(1);
    }
    echo "Exception caught: code=$code OK\n";
}

// Test 2: Insert with missing required fields
$path1 = __DIR__ . '/../test_error_handling_1_' . uniqid();
try {
    $c = ZVec::create($path1, $schema);
    $doc = new ZVecDoc('doc1');
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);  // Missing required 'id'
    $c->insert($doc);
    $c->close();
    echo "FAIL: Should fail for missing required field\n";
    exit(1);
} catch (ZVecException $e) {
    echo "Missing required field correctly rejected OK\n";
}

// Test 3: Insert with wrong data type (vector dimension mismatch)
$path2 = __DIR__ . '/../test_error_handling_2_' . uniqid();
$c = null;
try {
    $c = ZVec::create($path2, $schema);
    $doc = new ZVecDoc('doc2');
    $doc->setInt64('id', 999);
    $doc->setVectorFp32('v', [0.1, 0.2, 0.3, 0.4]);
    $c->insert($doc);
    
    // Query with invalid vector dimension
    $results = $c->query('v', [0.1, 0.2, 0.3], topk: 5);
    echo "FAIL: Should fail for wrong vector dimension\n";
    exit(1);
} catch (ZVecException $e) {
    echo "Wrong data type/dimension correctly rejected OK\n";
}

// Cleanup
if ($c !== null) {
    try {
        $c->close();
    } catch (Throwable $e) {
        // Ignore errors during cleanup
    }
}
exec("rm -rf " . escapeshellarg($path1) . " " . escapeshellarg($path2));

echo "All error handling scenarios work\n";
?>
--EXPECT--
Exception caught: code=3 OK
Missing required field correctly rejected OK
Wrong data type/dimension correctly rejected OK
All error handling scenarios work
