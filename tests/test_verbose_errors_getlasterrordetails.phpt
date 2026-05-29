--TEST--
Verbose errors: getLastErrorDetails respects verboseErrors flag
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip verboseErrors is FFI-only, not available with native zvec extension'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

// Test with verboseErrors=false (default)
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);
try {
    ZVec::open('/tmp/nonexistent_' . uniqid());
} catch (ZVecException $e) {
    // Expected
}

$details = ZVec::getLastErrorDetails();
if ($details['file'] === null && $details['line'] === 0 && $details['function'] === null) {
    echo "PASS: verboseErrors=false strips getLastErrorDetails\n";
} else {
    echo "FAIL: file={$details['file']} line={$details['line']} function={$details['function']}\n";
    exit(1);
}

// Test with verboseErrors=true
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN, verboseErrors: true);
try {
    ZVec::open('/tmp/nonexistent_' . uniqid());
} catch (ZVecException $e) {
    // Expected
}

$details = ZVec::getLastErrorDetails();
if ($details['file'] !== null && $details['line'] > 0 && $details['function'] !== null) {
    echo "PASS: verboseErrors=true preserves getLastErrorDetails\n";
} else {
    echo "FAIL: file={$details['file']} line={$details['line']} function={$details['function']}\n";
    exit(1);
}
?>
--EXPECT--
PASS: verboseErrors=false strips getLastErrorDetails
PASS: verboseErrors=true preserves getLastErrorDetails
