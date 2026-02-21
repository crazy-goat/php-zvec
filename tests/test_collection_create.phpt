--TEST--
Collection creation: schema, stats, path, options
--SKIPIF--
<?php if (!extension_loaded('ffi')) die('skip FFI extension not available'); ?>
--FILE--
<?php
require_once __DIR__ . '/../php/ZVec.php';

ZVec::init(logType: ZVec::LOG_CONSOLE, logLevel: ZVec::LOG_WARN);

$path = __DIR__ . '/../test_dbs/collection_create_' . uniqid();

$schema = new ZVecSchema('create_test');
$schema->setMaxDocCountPerSegment(1000)
    ->addInt64('id', nullable: false, withInvertIndex: true)
    ->addString('name', nullable: false, withInvertIndex: true)
    ->addVectorFp32('embedding', dimension: 4, metricType: ZVecSchema::METRIC_IP);

$c = ZVec::create($path, $schema);

$schemaStr = $c->schema();
assert(strpos($schemaStr, 'create_test') !== false, 'Schema should contain collection name');
assert(strpos($schemaStr, 'INT64') !== false, 'Schema should contain INT64');
assert(strpos($schemaStr, 'STRING') !== false || strpos($schemaStr, 'VARCHAR') !== false, 'Schema should contain STRING');
echo "Schema verified\n";

$stats = $c->stats();
assert(strpos($stats, 'doc_count') !== false || strpos($stats, 'segment_count') !== false, 'Stats should be present');
echo "Stats verified\n";

assert($c->path() === $path, 'Path should match');
echo "Path verified\n";

$opts = $c->options();
assert(isset($opts['read_only']), 'Options should contain read_only');
assert($opts['read_only'] === false, 'Collection should not be read-only');
echo "Options verified\n";

$c->close();

// Test invalid path (should fail)
$invalidPath = '/nonexistent_path/cannot_write_here';
try {
    $c2 = ZVec::create($invalidPath, $schema);
    echo "FAIL: Should fail for invalid path\n";
    exit(1);
} catch (ZVecException $e) {
    echo "Invalid path correctly rejected\n";
}

exec("rm -rf " . escapeshellarg($path));

echo "PASS: Collection creation works\n";
?>
--EXPECT--
Schema verified
Stats verified
Path verified
Options verified
Invalid path correctly rejected
PASS: Collection creation works
