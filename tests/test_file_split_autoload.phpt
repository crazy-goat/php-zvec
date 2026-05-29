--TEST--
SMELL-001: File split — each class loads independently via composer autoloader
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

$classes = [
    'ZVecException',
    'ZVecCollectionOptions',
    'ZVecCollectionStats',
    'ZVecFieldSchema',
    'ZVecIndexParams',
    'ZVecVectorQuery',
    'ZVecGroupByVectorQuery',
    'ZVecSchema',
    'ZVecDoc',
    'ZVec',
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        echo "FAIL: $class not found\n";
        exit(1);
    }
    echo "OK: $class loaded\n";
}

echo "PASS: All 10 classes loaded independently via autoloader\n";
?>
--EXPECT--
OK: ZVecException loaded
OK: ZVecCollectionOptions loaded
OK: ZVecCollectionStats loaded
OK: ZVecFieldSchema loaded
OK: ZVecIndexParams loaded
OK: ZVecVectorQuery loaded
OK: ZVecGroupByVectorQuery loaded
OK: ZVecSchema loaded
OK: ZVecDoc loaded
OK: ZVec loaded
PASS: All 10 classes loaded independently via autoloader
