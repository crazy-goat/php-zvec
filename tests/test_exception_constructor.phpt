--TEST--
ZVecException: constructor with various parameter combinations
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip These methods are FFI-only, not available with native zvec extension'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

// Test 1: Default constructor (no arguments)
$e = new ZVecException();
if ($e->getMessage() !== '') {
    echo "FAIL: Default message should be empty, got: " . $e->getMessage() . "\n";
    exit(1);
}
if ($e->getCode() !== 0) {
    echo "FAIL: Default code should be 0, got: " . $e->getCode() . "\n";
    exit(1);
}
if ($e->getErrorFile() !== null) {
    echo "FAIL: Default errorFile should be null\n";
    exit(1);
}
if ($e->getErrorLine() !== null) {
    echo "FAIL: Default errorLine should be null\n";
    exit(1);
}
if ($e->getErrorFunction() !== null) {
    echo "FAIL: Default errorFunction should be null\n";
    exit(1);
}
echo "Default constructor OK\n";

// Test 2: Constructor with message only
$e = new ZVecException('Something went wrong');
if ($e->getMessage() !== 'Something went wrong') {
    echo "FAIL: Message mismatch\n";
    exit(1);
}
if ($e->getCode() !== 0) {
    echo "FAIL: Code should be 0 when not specified\n";
    exit(1);
}
echo "Message-only constructor OK\n";

// Test 3: Constructor with message and code
$e = new ZVecException('Not found', 1);
if ($e->getMessage() !== 'Not found') {
    echo "FAIL: Message mismatch\n";
    exit(1);
}
if ($e->getCode() !== 1) {
    echo "FAIL: Code should be 1\n";
    exit(1);
}
echo "Message+code constructor OK\n";

// Test 4: Constructor with all params (message, code, previous, errorFile, errorLine, errorFunction)
$prev = new RuntimeException('Previous error');
$e = new ZVecException(
    message: 'Detailed error',
    code: 5,
    previous: $prev,
    errorFile: 'test.php',
    errorLine: 42,
    errorFunction: 'testFunction'
);
if ($e->getMessage() !== 'Detailed error') {
    echo "FAIL: Message mismatch\n";
    exit(1);
}
if ($e->getCode() !== 5) {
    echo "FAIL: Code mismatch\n";
    exit(1);
}
if ($e->getPrevious() !== $prev) {
    echo "FAIL: Previous exception mismatch\n";
    exit(1);
}
if ($e->getErrorFile() !== 'test.php') {
    echo "FAIL: errorFile mismatch: " . $e->getErrorFile() . "\n";
    exit(1);
}
if ($e->getErrorLine() !== 42) {
    echo "FAIL: errorLine mismatch: " . $e->getErrorLine() . "\n";
    exit(1);
}
if ($e->getErrorFunction() !== 'testFunction') {
    echo "FAIL: errorFunction mismatch\n";
    exit(1);
}
echo "Full params constructor OK\n";

// Test 5: Constructor with partial error details
$e = new ZVecException('Error', 3, errorFile: 'script.php', errorLine: 10);
if ($e->getErrorFile() !== 'script.php') {
    echo "FAIL: errorFile mismatch\n";
    exit(1);
}
if ($e->getErrorLine() !== 10) {
    echo "FAIL: errorLine mismatch\n";
    exit(1);
}
if ($e->getErrorFunction() !== null) {
    echo "FAIL: errorFunction should be null\n";
    exit(1);
}
echo "Partial error details OK\n";

// Test 6: Constructor with errorFunction only
$e = new ZVecException('Error', 7, errorFunction: 'someFunction');
if ($e->getErrorFunction() !== 'someFunction') {
    echo "FAIL: errorFunction mismatch\n";
    exit(1);
}
if ($e->getErrorFile() !== null) {
    echo "FAIL: errorFile should be null\n";
    exit(1);
}
if ($e->getErrorLine() !== null) {
    echo "FAIL: errorLine should be null\n";
    exit(1);
}
echo "Error function only OK\n";

// Test 7: Exception extends RuntimeException
if (!$e instanceof RuntimeException) {
    echo "FAIL: ZVecException should extend RuntimeException\n";
    exit(1);
}
echo "Extends RuntimeException OK\n";

echo "All constructor tests passed\n";
?>
--EXPECT--
Default constructor OK
Message-only constructor OK
Message+code constructor OK
Full params constructor OK
Partial error details OK
Error function only OK
Extends RuntimeException OK
All constructor tests passed
