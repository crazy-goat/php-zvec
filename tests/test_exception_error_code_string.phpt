--TEST--
ZVecException: getErrorCodeString() returns correct string for codes 0-10 and unrecognized
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip These methods are FFI-only, not available with native zvec extension'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

// Unit tests: test getErrorCodeString() directly without FFI
// We create exceptions manually to verify the error code string mapping

$tests = [
    0 => 'OK',
    1 => 'NOT_FOUND',
    2 => 'ALREADY_EXISTS',
    3 => 'INVALID_ARGUMENT',
    4 => 'PERMISSION_DENIED',
    5 => 'FAILED_PRECONDITION',
    6 => 'RESOURCE_EXHAUSTED',
    7 => 'UNAVAILABLE',
    8 => 'INTERNAL_ERROR',
    9 => 'NOT_SUPPORTED',
    10 => 'UNKNOWN',
];

foreach ($tests as $code => $expected) {
    $e = new ZVecException('test', $code);
    $actual = $e->getErrorCodeString();
    if ($actual !== $expected) {
        echo "FAIL: code=$code expected=$expected actual=$actual\n";
        exit(1);
    }
    echo "code=$code -> $actual OK\n";
}

// Test unrecognized code (default case)
$e = new ZVecException('test', 99);
$actual = $e->getErrorCodeString();
if ($actual !== 'UNRECOGNIZED') {
    echo "FAIL: code=99 expected=UNRECOGNIZED actual=$actual\n";
    exit(1);
}
echo "code=99 -> UNRECOGNIZED OK\n";

// Test negative code
$e = new ZVecException('test', -1);
$actual = $e->getErrorCodeString();
if ($actual !== 'UNRECOGNIZED') {
    echo "FAIL: code=-1 expected=UNRECOGNIZED actual=$actual\n";
    exit(1);
}
echo "code=-1 -> UNRECOGNIZED OK\n";

echo "All error code string tests passed\n";
?>
--EXPECT--
code=0 -> OK OK
code=1 -> NOT_FOUND OK
code=2 -> ALREADY_EXISTS OK
code=3 -> INVALID_ARGUMENT OK
code=4 -> PERMISSION_DENIED OK
code=5 -> FAILED_PRECONDITION OK
code=6 -> RESOURCE_EXHAUSTED OK
code=7 -> UNAVAILABLE OK
code=8 -> INTERNAL_ERROR OK
code=9 -> NOT_SUPPORTED OK
code=10 -> UNKNOWN OK
code=99 -> UNRECOGNIZED OK
code=-1 -> UNRECOGNIZED OK
All error code string tests passed
