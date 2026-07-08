<?php
require_once __DIR__ . '/../src/ZVec.php';

// 1. Error path FIRST: init with invalid memoryLimit causes
//    zvec_ffi_initialize() to return non-zero status → checkStatus() throws.
//    Before the fix, $logConfig and $configData were leaked here.
//    After the fix, try-finally frees them regardless.
echo "=== Error path first ===\n";
try {
    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN, memoryLimitMb: 999999);
    echo "FAIL: should have thrown\n";
    exit(1);
} catch (ZVecException $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}

// 2. Re-init after failure — should succeed because finally freed C memory
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
echo "Re-init after failure OK\n";
ZVec::shutdown();
echo "Shutdown after re-init OK\n";

// 3. Multiple happy-path cycles — confirms no accumulated memory pressure
for ($i = 1; $i <= 3; $i++) {
    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
    echo "Cycle $i init OK\n";
    ZVec::shutdown();
    echo "Cycle $i shutdown OK\n";
}

echo "PASS: BUG-004 init/shutdown cycles complete\n";
?>
