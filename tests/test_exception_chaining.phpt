--TEST--
ZVecException: exception chaining with previous Throwable
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip These methods are FFI-only, not available with native zvec extension'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

// Test 1: Chain a RuntimeException
$prev = new RuntimeException('Database connection failed');
$e = new ZVecException('Query execution error', 8, $prev);

if ($e->getPrevious() !== $prev) {
    echo "FAIL: Previous exception not set correctly\n";
    exit(1);
}
if ($e->getPrevious()->getMessage() !== 'Database connection failed') {
    echo "FAIL: Previous exception message mismatch\n";
    exit(1);
}
echo "RuntimeException chaining OK\n";

// Test 2: Chain another ZVecException
$inner = new ZVecException('Inner FFI error', 3);
$outer = new ZVecException('Operation failed', 8, $inner);

if ($outer->getPrevious() !== $inner) {
    echo "FAIL: Inner exception not set correctly\n";
    exit(1);
}
if ($outer->getPrevious()->getCode() !== 3) {
    echo "FAIL: Inner exception code mismatch\n";
    exit(1);
}
echo "ZVecException chaining OK\n";

// Test 3: Chain a custom Throwable
$custom = new \Error('Custom fatal error');
$e = new ZVecException('Wrapper error', 5, $custom);

if ($e->getPrevious() !== $custom) {
    echo "FAIL: Custom Throwable not set correctly\n";
    exit(1);
}
if ($e->getPrevious()->getMessage() !== 'Custom fatal error') {
    echo "FAIL: Custom Throwable message mismatch\n";
    exit(1);
}
echo "Custom Throwable chaining OK\n";

// Test 4: No previous exception (default null)
$e = new ZVecException('Simple error', 1);
if ($e->getPrevious() !== null) {
    echo "FAIL: Previous should be null when not set\n";
    exit(1);
}
echo "No previous exception OK\n";

// Test 5: Deep chaining (3 levels)
$level3 = new ZVecException('Level 3: C-level error', 3);
$level2 = new ZVecException('Level 2: FFI wrapper error', 5, $level3);
$level1 = new ZVecException('Level 1: PHP operation failed', 8, $level2);

if ($level1->getPrevious() !== $level2) {
    echo "FAIL: Level 1→2 chain broken\n";
    exit(1);
}
if ($level1->getPrevious()->getPrevious() !== $level3) {
    echo "FAIL: Level 2→3 chain broken\n";
    exit(1);
}
if ($level1->getPrevious()->getPrevious()->getPrevious() !== null) {
    echo "FAIL: Level 3 should have no previous\n";
    exit(1);
}
echo "Deep chaining (3 levels) OK\n";

// Test 6: getCode() on chained exceptions
$inner = new ZVecException('Inner error', 3);
$outer = new ZVecException('Outer error', 8, $inner);

if ($outer->getCode() !== 8) {
    echo "FAIL: Outer code should be 8\n";
    exit(1);
}
if ($outer->getPrevious()->getCode() !== 3) {
    echo "FAIL: Inner code should be 3\n";
    exit(1);
}
echo "Chained exception codes OK\n";

// Test 7: getMessage() on chained exceptions
if ($outer->getMessage() !== 'Outer error') {
    echo "FAIL: Outer message mismatch\n";
    exit(1);
}
if ($outer->getPrevious()->getMessage() !== 'Inner error') {
    echo "FAIL: Inner message mismatch\n";
    exit(1);
}
echo "Chained exception messages OK\n";

echo "All exception chaining tests passed\n";
?>
--EXPECT--
RuntimeException chaining OK
ZVecException chaining OK
Custom Throwable chaining OK
No previous exception OK
Deep chaining (3 levels) OK
Chained exception codes OK
Chained exception messages OK
All exception chaining tests passed
