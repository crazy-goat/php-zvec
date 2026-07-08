<?php
require_once __DIR__ . '/../src/ZVec.php';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN, verboseErrors: true);

// Test with nonexistent path - should get file/line/function
try {
    $coll = ZVec::open('/nonexistent/path_that_does_not_exist_xyz');
    echo "should not reach\n";
} catch (ZVecException $e) {
    echo "code: " . $e->getCode() . "\n";
    echo "has file: " . ($e->getErrorFile() !== null ? '1' : '0') . "\n";
    echo "has line: " . ($e->getErrorLine() !== null ? '1' : '0') . "\n";
    echo "has function: " . ($e->getErrorFunction() !== null ? '1' : '0') . "\n";
    echo "code string: " . $e->getErrorCodeString() . "\n";
}

// Test getLastErrorDetails static method
$details = ZVec::getLastErrorDetails();
echo "details[code]: " . $details['code'] . "\n";
echo "details has file: " . ($details['file'] !== null ? '1' : '0') . "\n";
echo "details has line: " . ($details['line'] > 0 ? '1' : '0') . "\n";
echo "details has function: " . ($details['function'] !== null ? '1' : '0') . "\n";

// Test clearError
ZVec::clearError();
$detailsAfter = ZVec::getLastErrorDetails();
echo "cleared code: " . $detailsAfter['code'] . "\n";
echo "cleared message: " . ($detailsAfter['message'] !== null ? 'not null' : 'null') . "\n";
echo "cleared file: " . ($detailsAfter['file'] !== null ? 'not null' : 'null') . "\n";

// Test error code strings for all known codes
$expectedStrings = [
    'OK',
    'NOT_FOUND',
    'ALREADY_EXISTS',
    'INVALID_ARGUMENT',
    'PERMISSION_DENIED',
    'FAILED_PRECONDITION',
    'RESOURCE_EXHAUSTED',
    'UNAVAILABLE',
    'INTERNAL_ERROR',
    'NOT_SUPPORTED',
    'UNKNOWN',
];

foreach ($expectedStrings as $i => $expected) {
    $e = new ZVecException('', $i);
    $actual = $e->getErrorCodeString();
    if ($actual !== $expected) {
        echo "FAIL: code $i expected '$expected' got '$actual'\n";
    }
}
echo "code strings OK\n";

echo "OK\n";
?>
