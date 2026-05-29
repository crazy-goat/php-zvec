--TEST--
Collection path traversal: directory traversal attacks rejected with allowedBasePath
--SKIPIF--
<?php if (!extension_loaded('zvec') && !extension_loaded('ffi')) die('skip Neither zvec extension nor FFI available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

$schema = new ZVecSchema('traversal_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$testDir = __DIR__ . '/../test_dbs';
ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN, allowedBasePath: $testDir);

// Test 1: Path within allowed base works
$validPath = $testDir . '/traversal_valid_' . uniqid();
try {
    $c = ZVec::create($validPath, $schema);
    $c->close();
    echo "Path within base accepted\n";
} finally {
    exec("rm -rf " . escapeshellarg($validPath));
}

// Test 2: Path escaping base with .. is rejected
$evilPath = $testDir . '/../evil_' . uniqid();
try {
    ZVec::create($evilPath, $schema);
    echo "FAIL: Traversal path should be rejected\n";
    exit(1);
} catch (ZVecException $e) {
    $msg = $e->getMessage();
    assert(str_contains($msg, 'Collection path not allowed'), "Expected 'Collection path not allowed', got: {$msg}");
    assert(str_contains($msg, 'must be within'), "Expected 'must be within', got: {$msg}");
    echo "Traversal with .. rejected\n";
}

// Test 3: Deeper traversal is rejected
$deepEvilPath = $testDir . '/../../etc/passwd';
try {
    ZVec::create($deepEvilPath, $schema);
    echo "FAIL: Deep traversal should be rejected\n";
    exit(1);
} catch (ZVecException $e) {
    $msg = $e->getMessage();
    assert(str_contains($msg, 'Collection path not allowed') || str_contains($msg, 'Parent directory does not exist'),
        "Expected traversal rejection, got: {$msg}");
    echo "Deep traversal rejected\n";
}

// Test 4: open() also validates
try {
    ZVec::open($evilPath);
    echo "FAIL: open() should also validate paths\n";
    exit(1);
} catch (ZVecException $e) {
    echo "open() traversal rejected\n";
}

// Test 5: Invalid base path throws
try {
    ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN, allowedBasePath: '/nonexistent_base_path');
    echo "FAIL: Nonexistent base should throw\n";
    exit(1);
} catch (ZVecException $e) {
    echo "Invalid base rejected\n";
}

echo "PASS: Path traversal prevention works\n";
?>
--EXPECT--
Path within base accepted
Traversal with .. rejected
Deep traversal rejected
open() traversal rejected
Invalid base rejected
PASS: Path traversal prevention works
