--TEST--
Collection path custom base: allowedBasePath restricts collection locations
--SKIPIF--
<?php if (extension_loaded('zvec')) die('skip Path validation is FFI-only'); ?>
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

$schema = new ZVecSchema('custom_base_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$allowedDir = __DIR__ . '/../test_dbs/custom_base_' . uniqid();
$otherDir = __DIR__ . '/../test_dbs/other_' . uniqid();
mkdir($allowedDir, 0755, true);
mkdir($otherDir, 0755, true);

try {
    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN, allowedBasePath: $allowedDir);

    // Test 1: Path within allowed base works
    $validPath = $allowedDir . '/collection_' . uniqid();
    $c = ZVec::create($validPath, $schema);
    $c->close();
    echo "Path within allowed base accepted\n";

    // Test 2: Path outside allowed base is rejected
    $outsidePath = $otherDir . '/collection_' . uniqid();
    try {
        ZVec::create($outsidePath, $schema);
        echo "FAIL: Path outside base should be rejected\n";
        exit(1);
    } catch (ZVecException $e) {
        $msg = $e->getMessage();
        assert(str_contains($msg, 'Collection path not allowed'), "Expected 'Collection path not allowed', got: {$msg}");
        assert(str_contains($msg, 'must be within'), "Expected 'must be within', got: {$msg}");
        echo "Path outside allowed base rejected\n";
    }

    // Test 3: open() also validates
    try {
        ZVec::open($outsidePath);
        echo "FAIL: open() should also validate\n";
        exit(1);
    } catch (ZVecException $e) {
        echo "open() validates path too\n";
    }

    echo "PASS: Custom base path works\n";
} finally {
    exec("rm -rf " . escapeshellarg($allowedDir));
    exec("rm -rf " . escapeshellarg($otherDir));
}
?>
--EXPECT--
Path within allowed base accepted
Path outside allowed base rejected
open() validates path too
PASS: Custom base path works
