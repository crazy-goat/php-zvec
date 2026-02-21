--TEST--
Bug 0004: max_doc_count_per_segment minimum threshold is 1000
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path1 = __DIR__ . '/../test_threshold_500_' . uniqid();
$path2 = __DIR__ . '/../test_threshold_1000_' . uniqid();

try {
    // Try to set below minimum threshold (should fail)
    try {
        $schema = new ZVecSchema('threshold_test');
        $schema->setMaxDocCountPerSegment(500)  // Below 1000 minimum
            ->addInt64('id', nullable: false)
            ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);
        
        $c = ZVec::create($path1, $schema);
        
        echo "FAIL: bug_0004 - Should reject max_doc_count_per_segment=500\n";
        exit(1);
    } catch (ZVecException $e) {
        if (strpos($e->getMessage(), 'max_doc_count_per_segment must >= 1000') !== false) {
            echo "Correctly rejected value below 1000\n";
        } else {
            echo "FAIL: bug_0004 - Wrong error message: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    // Verify 1000 is minimum acceptable value
    $schema2 = new ZVecSchema('threshold_test2');
    $schema2->setMaxDocCountPerSegment(1000)  // At minimum
        ->addInt64('id', nullable: false)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    
    $c2 = ZVec::create($path2, $schema2);
    $c2->close();
    
    echo "Accepted minimum value (1000) correctly\n";
    
} finally {
    if (is_dir($path1)) exec("rm -rf " . escapeshellarg($path1));
    if (is_dir($path2)) exec("rm -rf " . escapeshellarg($path2));
}

echo "PASS: bug_0004 - max_doc_count_per_segment threshold documented\n";
?>
--EXPECT--
Correctly rejected value below 1000
Accepted minimum value (1000) correctly
PASS: bug_0004 - max_doc_count_per_segment threshold documented
