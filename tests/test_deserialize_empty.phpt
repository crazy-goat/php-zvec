--TEST--
ZVecDoc::deserialize() rejects empty string
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

try {
    ZVecDoc::deserialize('');
    echo "FAIL: expected ZVecException\n";
} catch (ZVecException $e) {
    if (str_contains($e->getMessage(), 'too short')) {
        echo "PASS: empty string rejected\n";
    } else {
        echo "FAIL: unexpected message: " . $e->getMessage() . "\n";
    }
}
?>
--EXPECT--
PASS: empty string rejected
