--TEST--
ZVecDoc::deserialize() trust boundary — malformed data handling
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip native extension does not have minimum size guard'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

$allPassed = true;

// Test 1: 4 bytes of garbage — should throw, not crash (below minimum 8 bytes)
try {
    ZVecDoc::deserialize("\x00\x00\x00\x00");
    echo "FAIL: 4 zero bytes should be rejected\n";
    $allPassed = false;
} catch (ZVecException $e) {
    // Expected — too short
}

// Test 2: 7 bytes of garbage (still below minimum)
try {
    ZVecDoc::deserialize("\xff\xff\xff\xff\xff\xff\xff");
    echo "FAIL: 7 bytes should be rejected\n";
    $allPassed = false;
} catch (ZVecException $e) {
    // Expected — too short
}

// Test 3: Valid round-trip still works after malformed attempts
$doc = new ZVecDoc('test_pk');
$doc->setString('field', 'value');
$serialized = $doc->serialize();
$restored = ZVecDoc::deserialize($serialized);
if ($restored->getPk() !== 'test_pk' || $restored->getString('field') !== 'value') {
    echo "FAIL: valid round-trip failed after malformed attempts\n";
    $allPassed = false;
}

if ($allPassed) {
    echo "PASS: trust boundary tests pass\n";
}
?>
--EXPECT--
PASS: trust boundary tests pass
