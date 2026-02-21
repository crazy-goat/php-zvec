--TEST--
Collection destruction: destroy() removes directory and data
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_collection_destroy_' . uniqid();

$schema = new ZVecSchema('destroy_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);

// Insert some docs
for ($i = 1; $i <= 5; $i++) {
    $doc = new ZVecDoc("doc_$i");
    $doc->setInt64('id', $i)
        ->setVectorFp32('embedding', [0.1 * $i, 0.2 * $i, 0.3 * $i, 0.4 * $i]);
    $c->insert($doc);
}
echo "Inserted 5 docs\n";

// Destroy
$c->destroy();
echo "Collection destroyed\n";

// Verify directory removed
assert(!is_dir($path), 'Directory should be removed after destroy');
echo "Directory removed OK\n";

// Note: Using any methods on destroyed collection causes segfault
// This is expected behavior - destroy() invalidates the object

// Cleanup just in case
if (is_dir($path)) exec("rm -rf " . escapeshellarg($path));

echo "PASS: Collection destruction works\n";
?>
--EXPECT--
Inserted 5 docs
Collection destroyed
Directory removed OK
PASS: Collection destruction works
