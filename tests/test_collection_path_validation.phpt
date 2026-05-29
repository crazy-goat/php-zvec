--TEST--
Collection path validation: relative paths normalized, empty path rejected
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/path_validation_' . uniqid();

$schema = new ZVecSchema('path_validation_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

try {
    // Test 1: Absolute path works
    $c = ZVec::create($path, $schema);
    $c->close();
    echo "Absolute path accepted\n";

    // Test 2: Path with .. that resolves within test_dbs works
    $relativePath = __DIR__ . '/../test_dbs/path_validation_rel_' . uniqid();
    $c2 = ZVec::create($relativePath, $schema);
    $c2->close();
    echo "Relative path with .. accepted\n";

    // Test 3: Empty path rejected
    try {
        ZVec::create('', $schema);
        echo "FAIL: Empty path should be rejected\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "Empty path correctly rejected\n";
    }

    echo "PASS: Path validation works\n";
} finally {
    exec("rm -rf " . escapeshellarg($path));
    if (isset($relativePath)) {
        exec("rm -rf " . escapeshellarg($relativePath));
    }
}
?>
--EXPECT--
Absolute path accepted
Relative path with .. accepted
Empty path correctly rejected
PASS: Path validation works
