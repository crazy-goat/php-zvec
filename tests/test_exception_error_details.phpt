--TEST--
ZVecException: error details (getErrorFile, getErrorLine, getErrorFunction)
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip These methods are FFI-only, not available with native zvec extension'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

// Test 1: All error details set
$e = new ZVecException('Error', 5, errorFile: 'zvec_ffi.cc', errorLine: 128, errorFunction: 'zvec_collection_query');
if ($e->getErrorFile() !== 'zvec_ffi.cc') {
    echo "FAIL: getErrorFile() mismatch: " . $e->getErrorFile() . "\n";
    exit(1);
}
if ($e->getErrorLine() !== 128) {
    echo "FAIL: getErrorLine() mismatch: " . $e->getErrorLine() . "\n";
    exit(1);
}
if ($e->getErrorFunction() !== 'zvec_collection_query') {
    echo "FAIL: getErrorFunction() mismatch: " . $e->getErrorFunction() . "\n";
    exit(1);
}
echo "All error details set and retrieved OK\n";

// Test 2: Error line boundary values
$e = new ZVecException('Error', 3, errorLine: 0);
if ($e->getErrorLine() !== 0) {
    echo "FAIL: errorLine=0 should be accepted\n";
    exit(1);
}
echo "errorLine=0 OK\n";

$e = new ZVecException('Error', 3, errorLine: PHP_INT_MAX);
if ($e->getErrorLine() !== PHP_INT_MAX) {
    echo "FAIL: errorLine=PHP_INT_MAX should be accepted\n";
    exit(1);
}
echo "errorLine=PHP_INT_MAX OK\n";

// Test 3: Empty strings for file and function
$e = new ZVecException('Error', 3, errorFile: '', errorFunction: '');
if ($e->getErrorFile() !== '') {
    echo "FAIL: Empty errorFile should be accepted\n";
    exit(1);
}
if ($e->getErrorFunction() !== '') {
    echo "FAIL: Empty errorFunction should be accepted\n";
    exit(1);
}
echo "Empty strings for file/function OK\n";

// Test 4: Long file path
$longPath = str_repeat('a', 200) . '.cc';
$e = new ZVecException('Error', 3, errorFile: $longPath);
if ($e->getErrorFile() !== $longPath) {
    echo "FAIL: Long errorFile path mismatch\n";
    exit(1);
}
echo "Long file path OK\n";

// Test 5: Unicode in error details
$e = new ZVecException('Error', 5, errorFile: 'źródło.cc', errorFunction: 'funkcja_testowa');
if ($e->getErrorFile() !== 'źródło.cc') {
    echo "FAIL: Unicode errorFile mismatch\n";
    exit(1);
}
if ($e->getErrorFunction() !== 'funkcja_testowa') {
    echo "FAIL: Unicode errorFunction mismatch\n";
    exit(1);
}
echo "Unicode error details OK\n";

// Test 6: Error details are independent on different instances
$e1 = new ZVecException('Error 1', 1, errorFile: 'file1.cc', errorLine: 10, errorFunction: 'func1');
$e2 = new ZVecException('Error 2', 2, errorFile: 'file2.cc', errorLine: 20, errorFunction: 'func2');

if ($e1->getErrorFile() !== 'file1.cc' || $e2->getErrorFile() !== 'file2.cc') {
    echo "FAIL: Error details should be independent per instance\n";
    exit(1);
}
if ($e1->getErrorLine() !== 10 || $e2->getErrorLine() !== 20) {
    echo "FAIL: Error lines should be independent per instance\n";
    exit(1);
}
if ($e1->getErrorFunction() !== 'func1' || $e2->getErrorFunction() !== 'func2') {
    echo "FAIL: Error functions should be independent per instance\n";
    exit(1);
}
echo "Independent error details on different instances OK\n";

// Test 7: Error details preserved through chaining
$inner = new ZVecException('Inner', 3, errorFile: 'inner.cc', errorLine: 5, errorFunction: 'inner_func');
$outer = new ZVecException('Outer', 8, $inner, errorFile: 'outer.cc', errorLine: 100, errorFunction: 'outer_func');

if ($outer->getErrorFile() !== 'outer.cc') {
    echo "FAIL: Outer errorFile should be 'outer.cc'\n";
    exit(1);
}
if ($outer->getPrevious()->getErrorFile() !== 'inner.cc') {
    echo "FAIL: Inner errorFile should be 'inner.cc'\n";
    exit(1);
}
if ($outer->getPrevious()->getErrorLine() !== 5) {
    echo "FAIL: Inner errorLine should be 5\n";
    exit(1);
}
if ($outer->getPrevious()->getErrorFunction() !== 'inner_func') {
    echo "FAIL: Inner errorFunction should be 'inner_func'\n";
    exit(1);
}
echo "Error details preserved through chaining OK\n";

echo "All error details tests passed\n";
?>
--EXPECT--
All error details set and retrieved OK
errorLine=0 OK
errorLine=PHP_INT_MAX OK
Empty strings for file/function OK
Long file path OK
Unicode error details OK
Independent error details on different instances OK
Error details preserved through chaining OK
All error details tests passed
