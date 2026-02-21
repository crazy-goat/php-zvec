<?php
/**
 * Bug reproduction: max_doc_count_per_segment minimum threshold
 * 
 * Expected: Can set small values (e.g., 2) for testing
 * Actual: ZVecException with "max_doc_count_per_segment must >= 1000"
 * 
 * Root cause: C++ constant MAX_DOC_COUNT_PER_SEGMENT_MIN_THRESHOLD = 1000
 * Location: zvec/src/include/zvec/db/schema.h:25
 * 
 * This makes testing segment operations difficult as you need
 * 1000+ docs per segment and 2000+ docs to create multiple segments.
 */

require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

// Try to set below minimum threshold
try {
    $schema = new ZVecSchema('threshold_test');
    $schema->setMaxDocCountPerSegment(500)  // Below 1000 minimum
        ->addInt64('id', nullable: false)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    
    $path = __DIR__ . '/../test_threshold_500';
    if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));
    
    $c = ZVec::create($path, $schema);
    
    echo "FAIL: bug_0004 - Should reject max_doc_count_per_segment=500\n";
    exit(1);
} catch (ZVecException $e) {
    if (strpos($e->getMessage(), 'max_doc_count_per_segment must >= 1000') !== false) {
        echo "  Correctly rejected value below 1000\n";
        echo "  Error: " . $e->getMessage() . "\n";
    } else {
        echo "FAIL: bug_0004 - Wrong error message: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Verify 1000 is minimum acceptable value
try {
    $schema2 = new ZVecSchema('threshold_test2');
    $schema2->setMaxDocCountPerSegment(1000)  // At minimum
        ->addInt64('id', nullable: false)
        ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);
    
    $path2 = __DIR__ . '/../test_threshold_1000';
    if (is_dir($path2)) exec("rm -rf " . escapeshellarg($path2));
    
    $c2 = ZVec::create($path2, $schema2);
    $c2->close();
    exec("rm -rf " . escapeshellarg($path2));
    
    echo "  Accepted minimum value (1000) correctly\n";
} catch (ZVecException $e) {
    echo "FAIL: bug_0004 - Should accept max_doc_count_per_segment=1000: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nNOTE: C++ constant MAX_DOC_COUNT_PER_SEGMENT_MIN_THRESHOLD = 1000\n";
echo "This requires 2000+ docs to test multi-segment scenarios.\n";
echo "PASS: bug_0004 - max_doc_count_per_segment threshold documented\n";

// Cleanup
if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));
