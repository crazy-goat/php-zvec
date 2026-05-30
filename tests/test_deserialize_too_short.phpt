--TEST--
ZVecDoc::deserialize() rejects data shorter than 4 bytes
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

$cases = ['', 'a', 'ab', 'abc', 'abcd', 'abcde', 'abcdef', 'abcdefg'];
$allPassed = true;

foreach ($cases as $data) {
    try {
        ZVecDoc::deserialize($data);
        echo "FAIL: expected ZVecException for " . strlen($data) . " bytes\n";
        $allPassed = false;
    } catch (ZVecException $e) {
        if (!str_contains($e->getMessage(), 'too short')) {
            echo "FAIL: unexpected message for " . strlen($data) . " bytes: " . $e->getMessage() . "\n";
            $allPassed = false;
        }
    }
}

if ($allPassed) {
    echo "PASS: all short inputs rejected\n";
}
?>
--EXPECT--
PASS: all short inputs rejected
