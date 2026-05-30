--TEST--
Memory leak: serialize/deserialize buffer does not leak
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../src/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$memBefore = memory_get_usage();

// 100x serialize/deserialize cycle
for ($i = 0; $i < 100; $i++) {
    $doc = new ZVecDoc('test_pk');
    $doc->setInt64('id', $i)
        ->setString('name', "item_{$i}")
        ->setFloat('score', $i * 1.5)
        ->setVectorFp32('embedding', [(float)$i, 1.0, 0.0, 0.0]);

    $serialized = $doc->serialize();
    assert(strlen($serialized) > 0, 'Serialized data should not be empty');

    $deserialized = ZVecDoc::deserialize($serialized);
    assert($deserialized->getPk() === 'test_pk', 'PK should match');
    assert($deserialized->getInt64('id') === $i, 'ID should match');
    assert($deserialized->getString('name') === "item_{$i}", 'Name should match');
}

$memAfter = memory_get_usage();
$delta = $memAfter - $memBefore;

// Allow 500KB tolerance
if ($delta > 500 * 1024) {
    echo "FAIL: Memory grew by {$delta} bytes (threshold 500KB)\n";
    exit(1);
}

echo "100x serialize/deserialize OK (delta: {$delta} bytes)\n";
echo "PASS: Serialize/deserialize buffer does not leak\n";
?>
--EXPECTF--
100x serialize/deserialize OK (delta: %d bytes)
PASS: Serialize/deserialize buffer does not leak
