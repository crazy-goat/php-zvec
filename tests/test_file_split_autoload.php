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
