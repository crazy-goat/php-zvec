<?php
require_once __DIR__ . '/../src/ZVec.php';

// Test error path: init with invalid memoryLimit causes
// zvec_ffi_initialize() to return non-zero status → checkStatus() throws.
// The try-finally in init() must free $logConfig and $configData.
echo "=== Error path test ===\n";
try {
    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN, memoryLimitMb: 999999);
    echo "FAIL: should have thrown\n";
    exit(1);
} catch (ZVecException $e) {
    echo "Caught expected error: " . $e->getMessage() . "\n";
}

// Re-init after failure — should succeed because finally freed C memory
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
echo "Re-init after error OK\n";

$memBefore = memory_get_usage();

// 20x error path cycles — confirms no accumulated C memory pressure
for ($i = 0; $i < 20; $i++) {
    try {
        ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN, memoryLimitMb: 999999);
    } catch (ZVecException $e) {
        // Expected
    }
    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
}

$memAfter = memory_get_usage();
$delta = $memAfter - $memBefore;

if ($delta > 500 * 1024) {
    echo "FAIL: Memory grew by {$delta} bytes (threshold 500KB)\n";
    exit(1);
}

echo "20x error/recovery cycles OK (delta: {$delta} bytes)\n";
echo "PASS: Init error path does not leak C allocations\n";
?>
